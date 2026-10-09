<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/user2fa.class.php
 * \ingroup    totp2fa
 * \brief      User 2FA settings management
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/totp2fa/class/totp.class.php');
dol_include_once('/totp2fa/class/totp2fa_activity.class.php');

/**
 * User2FA Class - Manages user 2FA settings
 */
class User2FA extends CommonObject
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Element type
     */
    public $element = 'user2fa';

    /**
     * @var string Table name
     */
    public $table_element = 'totp2fa_user_settings';

    /**
     * @var int User ID
     */
    public $fk_user;

    /**
     * @var string Encrypted secret
     */
    public $secret;

    /**
     * @var int Is 2FA enabled (0 or 1)
     */
    public $is_enabled = 0;

    /**
     * @var string Last used code
     */
    public $last_used_code;

    /**
     * @var int Last used time
     */
    public $last_used_time;

    /**
     * @var int Failed attempts counter
     */
    public $failed_attempts = 0;

    /**
     * @var int Last failed attempt timestamp
     */
    public $last_failed_attempt;

    /**
     * @var TOTP TOTP instance
     */
    private $totp;

    /**
     * @var string|null Binary key for the current (v2) secret format, null if no key configured yet
     */
    private $encryptionKey;

    /**
     * @var string[] Binary keys accepted when decrypting v2 secrets or checking backup codes
     */
    private $decryptionKeys = array();

    /**
     * @var string Key derived from DB credentials, used by the legacy secret format
     */
    private $legacyKey;

    /**
     * @var string[] Raw configured keys accepted for legacy secrets
     */
    private $legacyKeyAlternatives = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->totp = new TOTP();

        global $conf;

        // Legacy CBC secrets used either the DB-derived key or the raw configured key.
        $this->legacyKey = hash('sha256', $conf->db->name.$conf->db->user, true);
        $configuredKeys = self::getConfiguredKeys($db);
        $this->legacyKeyAlternatives = $configuredKeys;

        foreach ($configuredKeys as $configuredKey) {
            $binaryKey = hash('sha256', $configuredKey, true);
            if (!in_array($binaryKey, $this->decryptionKeys, true)) {
                $this->decryptionKeys[] = $binaryKey;
            }
        }
        $this->encryptionKey = isset($this->decryptionKeys[0]) ? $this->decryptionKeys[0] : null;
    }

    /**
     * Get all configured encryption keys.
     *
     * Versions 1.5.0 and 1.5.1 stored the key on the active entity. During login Dolibarr has
     * not selected the user's entity yet, so every entity key must remain available for reading.
     * The first key is canonical for new data: conf.php first, then database constants ordered
     * with the global entity first.
     *
     * @param DoliDB|null $db Database handler
     * @return string[] Configured keys
     */
    public static function getConfiguredKeys($db = null)
    {
        global $conf;

        $keys = array();
        $addKey = function ($key) use (&$keys) {
            $key = (string) $key;
            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        };

        if (!empty($GLOBALS['dolibarr_main_totp2fa_encryption_key'])) {
            $addKey($GLOBALS['dolibarr_main_totp2fa_encryption_key']);
        }

        if ($db !== null) {
            require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

            $sql = "SELECT value FROM ".MAIN_DB_PREFIX."const";
            $sql .= " WHERE name = 'TOTP2FA_ENCRYPTION_KEY'";
            $sql .= " AND value IS NOT NULL AND value <> ''";
            $sql .= " ORDER BY entity ASC";
            $resql = $db->query($sql);
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $decryptedValue = dolDecrypt($obj->value);
                    if (is_string($decryptedValue) && $decryptedValue !== '') {
                        $addKey($decryptedValue);
                    } else {
                        dol_syslog(__METHOD__.": unable to decrypt an encryption key", LOG_WARNING);
                    }
                }
                $db->free($resql);
            } else {
                dol_syslog(__METHOD__.": unable to load encryption keys: ".$db->lasterror(), LOG_WARNING);
            }
        }

        if (!empty($conf->global->TOTP2FA_ENCRYPTION_KEY)) {
            $addKey($conf->global->TOTP2FA_ENCRYPTION_KEY);
        }

        return $keys;
    }

    /**
     * Get the canonical configured encryption key.
     *
     * @param DoliDB|null $db Database handler
     * @return string Key or '' if none configured
     */
    public static function getConfiguredKey($db = null)
    {
        $keys = self::getConfiguredKeys($db);
        return isset($keys[0]) ? $keys[0] : '';
    }

    /**
     * Generate a random encryption key and store it, unless one is already configured.
     *
     * @param DoliDB $db Database handler
     * @return bool True if a key exists afterwards
     */
    public static function provisionKey($db)
    {
        global $conf;

        if (self::getConfiguredKey($db) !== '') {
            return true;
        }

        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $key = bin2hex(random_bytes(32));
        if (dolibarr_set_const($db, 'TOTP2FA_ENCRYPTION_KEY', $key, 'chaine', 0, 'TOTP2FA secret encryption key', 0) <= 0) {
            return false;
        }
        $conf->global->TOTP2FA_ENCRYPTION_KEY = $key;
        return true;
    }

    /**
     * Re-encrypt all secrets stored in the legacy format with the configured key (idempotent).
     * Legacy format: AES-256-CBC without authentication. New format: "v2:" + AES-256-GCM.
     *
     * @return array Counters: migrated, failed, already
     */
    public function migrateSecrets()
    {
        $result = array('migrated' => 0, 'failed' => 0, 'already' => 0);
        if ($this->encryptionKey === null) {
            return $result;
        }

        $sql = "SELECT rowid, secret FROM ".MAIN_DB_PREFIX.$this->table_element;
        $resql = $this->db->query($sql);
        if (!$resql) {
            return $result;
        }

        $rows = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $rows[] = $obj;
        }

        foreach ($rows as $obj) {
            if (strpos($obj->secret, 'v2:') === 0) {
                $result['already']++;
                continue;
            }
            $plain = $this->decryptLegacy($obj->secret);
            if (!is_string($plain) || !preg_match('/^[A-Z2-7]{16,}$/', $plain)) {
                $result['failed']++;
                continue;
            }
            $upd = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
            $upd .= " SET secret = '".$this->db->escape($this->encryptSecret($plain))."'";
            $upd .= " WHERE rowid = ".(int) $obj->rowid;
            if ($this->db->query($upd)) {
                $result['migrated']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Fetch user 2FA settings
     *
     * @param int $fk_user User ID
     * @return int <0 if KO, >0 if OK, 0 if not found
     */
    public function fetch($fk_user)
    {
        $sql = "SELECT rowid, fk_user, secret, is_enabled, last_used_code, last_used_time,";
        $sql .= " failed_attempts, last_failed_attempt, date_created, date_modified";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_user = ".(int)$fk_user;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->id = $obj->rowid;
                $this->fk_user = $obj->fk_user;
                $this->secret = $obj->secret;
                $this->is_enabled = $obj->is_enabled;
                $this->last_used_code = $obj->last_used_code;
                $this->last_used_time = $obj->last_used_time;
                $this->failed_attempts = $obj->failed_attempts;
                $this->last_failed_attempt = $obj->last_failed_attempt;
                $this->date_creation = $this->db->jdate($obj->date_created);
                $this->date_modification = $this->db->jdate($obj->date_modified);

                $this->db->free($resql);
                return 1;
            }
            $this->db->free($resql);
            return 0;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Create new 2FA settings for user
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function create($user)
    {
        global $conf;

        // Generate new secret
        $secret = $this->totp->generateSecret();
        $encryptedSecret = $this->encryptSecret($secret);

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " (fk_user, secret, is_enabled, date_created)";
        $sql .= " VALUES (";
        $sql .= " ".(int)$this->fk_user.",";
        $sql .= " '".$this->db->escape($encryptedSecret)."',";
        $sql .= " 0,"; // Initially disabled
        $sql .= " '".$this->db->idate(dol_now())."'";
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->secret = $encryptedSecret;

            // Store plain secret temporarily for QR code generation
            $this->_plainSecret = $secret;

            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update 2FA settings
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function update($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " SET is_enabled = ".(int)$this->is_enabled.",";
        $sql .= " last_used_code = ".($this->last_used_code ? "'".$this->db->escape($this->last_used_code)."'" : "NULL").",";
        $sql .= " last_used_time = ".($this->last_used_time ? (int)$this->last_used_time : "NULL").",";
        $sql .= " failed_attempts = ".(int)$this->failed_attempts.",";
        $sql .= " last_failed_attempt = ".($this->last_failed_attempt ? (int)$this->last_failed_attempt : "NULL").",";
        $sql .= " date_modified = '".$this->db->idate(dol_now())."'";
        $sql .= " WHERE rowid = ".(int)$this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Delete 2FA settings
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user)
    {
        $this->revokeTrustedDevices();

        // Delete backup codes first
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_backup_codes";
        $sql .= " WHERE fk_user = ".(int)$this->fk_user;
        $this->db->query($sql);

        // Delete settings
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Verify TOTP code
     *
     * @param string $code Code to verify
     * @return bool True if valid
     */
    public function verifyCode($code)
    {
        if ($this->isLocked()) {
            return false;
        }

        // A code stays valid for the whole drift window, so reject re-use for that long
        if ($this->last_used_code !== null && (string) $this->last_used_code === (string) $code && (time() - $this->last_used_time) < 120) {
            $this->error = 'This code has already been used.';
            $this->incrementFailedAttempts();
            return false;
        }

        // Decrypt secret
        $secret = $this->decryptSecret($this->secret);
        if (!is_string($secret) || $secret === '') {
            $this->error = 'Secret could not be decrypted.';
            return false;
        }

        // Verify code
        $isValid = $this->totp->verifyCode($secret, $code);

        if ($isValid) {
            // Reset failed attempts
            $this->failed_attempts = 0;
            $this->last_used_code = $code;
            $this->last_used_time = time();
            $this->update(null);
            return true;
        } else {
            $this->error = 'Invalid code.';
            $this->incrementFailedAttempts();
            return false;
        }
    }

    /**
     * Increment failed attempts counter
     *
     * @return void
     */
    private function isLocked()
    {
        if ($this->failed_attempts >= 10) {
            if ((time() - $this->last_failed_attempt) < 300) {
                $this->error = 'Too many failed attempts. Please wait before trying again.';
                return true;
            }
            $this->failed_attempts = 0;
        }
        return false;
    }

    /**
     * Increment the failed attempts counter
     *
     * @return void
     */
    private function incrementFailedAttempts()
    {
        $this->failed_attempts++;
        $this->last_failed_attempt = time();
        $this->update(null);
    }

    /**
     * Get plain secret (only available after creation)
     *
     * @return string|null Plain secret or null
     */
    public function getPlainSecret()
    {
        if (isset($this->_plainSecret)) {
            return $this->_plainSecret;
        }
        // For existing records, decrypt
        return $this->decryptSecret($this->secret);
    }

    /**
     * Get QR code URL for authenticator apps
     *
     * @param string $userEmail User email/login
     * @param string $issuer Issuer name
     * @return string QR code URL
     */
    public function getQRCodeUrl($userEmail, $issuer = 'Dolibarr')
    {
        $secret = $this->getPlainSecret();
        return $this->totp->getQRCodeUrl($secret, $userEmail, $issuer);
    }

    /**
     * Generate backup codes
     *
     * @param int $count Number of codes to generate (default 10)
     * @return array Array of backup codes
     */
    public function generateBackupCodes($count = 10)
    {
        global $user;

        $codes = array();

        // Regenerating invalidates all previous codes
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."totp2fa_backup_codes WHERE fk_user = ".(int) $this->fk_user);

        for ($i = 0; $i < $count; $i++) {
            // Generate random 8-digit code
            $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $code = substr($code, 0, 4).'-'.substr($code, 4, 4); // Format: 1234-5678
            $codeHash = $this->hashBackupCode($code);

            // Store in database
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_backup_codes";
            $sql .= " (fk_user, code_hash, is_used, date_created)";
            $sql .= " VALUES (";
            $sql .= " ".(int)$this->fk_user.",";
            $sql .= " '".$this->db->escape($codeHash)."',";
            $sql .= " 0,";
            $sql .= " '".$this->db->idate(dol_now())."'";
            $sql .= ")";

            $this->db->query($sql);

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Verify backup code
     *
     * @param string $code Backup code
     * @return bool True if valid
     */
    public function verifyBackupCode($code)
    {
        if ($this->isLocked()) {
            return false;
        }

        $hashes = array();
        foreach ($this->decryptionKeys as $key) {
            $hashes[] = hash_hmac('sha256', $code, $key);
        }
        $legacyHash = hash('sha256', $code);
        if (!in_array($legacyHash, $hashes, true)) {
            $hashes[] = $legacyHash;
        }

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."totp2fa_backup_codes";
        $sql .= " WHERE fk_user = ".(int)$this->fk_user;
        $sql .= " AND code_hash IN ('".implode("','", array_map(array($this->db, 'escape'), $hashes))."')";
        $sql .= " AND is_used = 0";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);

            // Mark as used
            $sql = "UPDATE ".MAIN_DB_PREFIX."totp2fa_backup_codes";
            $sql .= " SET is_used = 1, date_used = '".$this->db->idate(dol_now())."'";
            $sql .= " WHERE rowid = ".(int)$obj->rowid;
            $this->db->query($sql);

            // Log backup code usage
            $this->logBackupCodeUsed();

            $this->failed_attempts = 0;
            $this->update(null);

            return true;
        }

        $this->error = 'Invalid code.';
        $this->incrementFailedAttempts();
        return false;
    }

    /**
     * Hash a backup code. Keyed (HMAC) when an encryption key is configured, so a database
     * leak alone does not allow brute-forcing the 8-digit codes.
     *
     * @param string $code Backup code
     * @return string Hex hash
     */
    private function hashBackupCode($code)
    {
        if ($this->encryptionKey !== null) {
            return hash_hmac('sha256', $code, $this->encryptionKey);
        }
        return hash('sha256', $code);
    }

    /**
     * Encrypt secret (AES-256-GCM, "v2:" prefix). Falls back to the legacy format only
     * while no key is configured.
     *
     * @param string $plaintext Plain secret
     * @return string Encrypted secret
     */
    private function encryptSecret($plaintext)
    {
        if ($this->encryptionKey === null) {
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            return base64_encode($iv.openssl_encrypt($plaintext, 'aes-256-cbc', $this->legacyKey, 0, $iv));
        }

        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return 'v2:'.base64_encode($iv.$tag.$ct);
    }

    /**
     * Decrypt secret (v2 or legacy format)
     *
     * @param string $encrypted Encrypted secret
     * @return string|false Plain secret, false on failure
     */
    private function decryptSecret($encrypted)
    {
        if (strpos($encrypted, 'v2:') === 0) {
            if (empty($this->decryptionKeys)) {
                return false;
            }
            $data = base64_decode(substr($encrypted, 3), true);
            if ($data === false || strlen($data) < 29) {
                return false;
            }
            foreach ($this->decryptionKeys as $key) {
                $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
                if (is_string($plain) && preg_match('/^[A-Z2-7]{16,}$/', $plain)) {
                    return $plain;
                }
            }
            return false;
        }

        return $this->decryptLegacy($encrypted);
    }

    /**
     * Decrypt a secret stored in the legacy format (AES-256-CBC, no authentication)
     *
     * @param string $encrypted Encrypted secret (base64)
     * @return string|false Plain secret, false on failure
     */
    private function decryptLegacy($encrypted)
    {
        $data = base64_decode($encrypted);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $ct = substr($data, $ivLength);

        $keys = array_merge($this->legacyKeyAlternatives, array($this->legacyKey));
        foreach ($keys as $key) {
            $plain = openssl_decrypt($ct, 'aes-256-cbc', $key, 0, $iv);
            if (is_string($plain) && preg_match('/^[A-Z2-7]{16,}$/', $plain)) {
                return $plain;
            }
        }

        return false;
    }

    /**
     * Enable 2FA and send notification
     *
     * @return int <0 if KO, >0 if OK
     */
    public function enable()
    {
        $this->is_enabled = 1;
        $result = $this->update(null);

        if ($result > 0) {
            // Log activity
            $activity = new Totp2faActivity($this->db);
            $activity->log($this->fk_user, Totp2faActivity::ACTION_2FA_ENABLED);

            // Send email notification
            $this->sendNotificationEmail('enabled');
        }

        return $result;
    }

    /**
     * Disable 2FA and send notification
     *
     * @return int <0 if KO, >0 if OK
     */
    public function disable()
    {
        $this->revokeTrustedDevices();
        $this->is_enabled = 0;
        $result = $this->update(null);

        if ($result > 0) {
            // Log activity
            $activity = new Totp2faActivity($this->db);
            $activity->log($this->fk_user, Totp2faActivity::ACTION_2FA_DISABLED);

            // Send email notification
            $this->sendNotificationEmail('disabled');
        }

        return $result;
    }

    /**
     * Revoke all trusted devices of this user
     *
     * @return void
     */
    private function revokeTrustedDevices()
    {
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices WHERE fk_user = ".(int) $this->fk_user);
    }

    /**
     * Log successful login
     *
     * @return void
     */
    public function logLoginSuccess()
    {
        $activity = new Totp2faActivity($this->db);
        $activity->log($this->fk_user, Totp2faActivity::ACTION_LOGIN_SUCCESS);
    }

    /**
     * Log failed login attempt and check for notifications
     *
     * @return void
     */
    public function logLoginFailed()
    {
        $activity = new Totp2faActivity($this->db);
        $activity->log($this->fk_user, Totp2faActivity::ACTION_LOGIN_FAILED);

        // Check if we need to send a warning email (3 or more failed attempts in last 5 minutes)
        // Only send once per 5 minute window (on exactly 3rd attempt)
        $recentFails = $activity->countRecentFailedAttempts($this->fk_user, 5);
        if ($recentFails == 3) {
            $result = $this->sendFailedAttemptsEmail($recentFails);
            // Log email send attempt for debugging
            if (!$result) {
                $activity->log($this->fk_user, 'email_failed', 'Failed to send warning email');
            }
        }
    }

    /**
     * Log backup code usage
     *
     * @return void
     */
    public function logBackupCodeUsed()
    {
        $activity = new Totp2faActivity($this->db);
        $activity->log($this->fk_user, Totp2faActivity::ACTION_BACKUP_CODE_USED);
    }

    /**
     * Log secret regeneration
     *
     * @return void
     */
    public function logSecretRegenerated()
    {
        $activity = new Totp2faActivity($this->db);
        $activity->log($this->fk_user, Totp2faActivity::ACTION_SECRET_REGENERATED);
    }

    /**
     * Send notification email for 2FA status change
     *
     * @param string $type 'enabled' or 'disabled'
     * @return bool True if sent
     */
    private function sendNotificationEmail($type)
    {
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
        require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

        $langs->load("totp2fa@totp2fa");

        // Get user info
        $userObj = new User($this->db);
        $userObj->fetch($this->fk_user);

        if (empty($userObj->email)) {
            return false;
        }

        // Get user name (fallback to login if name is empty)
        $userName = trim($userObj->firstname . ' ' . $userObj->lastname);
        if (empty($userName)) {
            $userName = $userObj->login;
        }

        // Build email with proper encoding
        if ($type === 'enabled') {
            $subject = "2FA wurde für Ihr Konto aktiviert";
            $body = "Hallo " . $userName . ",\n\n";
            $body .= "die Zwei-Faktor-Authentifizierung wurde soeben für Ihr Konto aktiviert.\n\n";
            $body .= "Wenn Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie bitte umgehend Ihren Administrator.\n\n";
            $body .= "Mit freundlichen Grüßen";
        } else {
            $subject = "2FA wurde für Ihr Konto deaktiviert";
            $body = "Hallo " . $userName . ",\n\n";
            $body .= "die Zwei-Faktor-Authentifizierung wurde für Ihr Konto deaktiviert.\n\n";
            $body .= "Wenn Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie bitte umgehend Ihren Administrator.\n\n";
            $body .= "Mit freundlichen Grüßen";
        }

        // Add company signature
        if (!empty($conf->global->MAIN_INFO_SOCIETE_NOM)) {
            $body .= "\n\n" . $conf->global->MAIN_INFO_SOCIETE_NOM;
        }

        // Send email
        $from = !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@'.$_SERVER['SERVER_NAME'];

        $mail = new CMailFile(
            $subject,
            $userObj->email,
            $from,
            $body,
            array(),
            array(),
            array(),
            '',
            '',
            0,
            0
        );

        return $mail->sendfile();
    }

    /**
     * Send warning email for failed login attempts
     *
     * @param int $attempts Number of failed attempts
     * @return bool True if sent
     */
    private function sendFailedAttemptsEmail($attempts)
    {
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
        require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

        $langs->load("totp2fa@totp2fa");

        // Get user info
        $userObj = new User($this->db);
        $userObj->fetch($this->fk_user);

        if (empty($userObj->email)) {
            return false;
        }

        // Get IP and time
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
        $time = dol_print_date(dol_now(), 'dayhour');

        // Prepare email subject
        $subject = $langs->trans("Email2FAFailedAttemptsSubject");

        // Build body with proper newlines (translation \n doesn't work well with sprintf)
        $userName = $userObj->getFullName($langs);
        $body = "Hallo " . $userName . ",\n\n";
        $body .= "es wurden " . (int)$attempts . " fehlgeschlagene Versuche registriert, sich mit Ihrem Konto anzumelden.\n\n";
        $body .= "IP-Adresse: " . $ip . "\n";
        $body .= "Zeitpunkt: " . $time . "\n\n";
        $body .= "Wenn Sie diese Anmeldeversuche nicht unternommen haben, ändern Sie bitte umgehend Ihr Passwort.\n\n";
        $body .= "Mit freundlichen Grüßen";

        // Add company signature
        if (!empty($conf->global->MAIN_INFO_SOCIETE_NOM)) {
            $body .= "\n\n" . $conf->global->MAIN_INFO_SOCIETE_NOM;
        }

        // Send email
        $from = !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@'.$_SERVER['SERVER_NAME'];

        $mail = new CMailFile(
            $subject,
            $userObj->email,
            $from,
            $body,
            array(),
            array(),
            array(),
            '',
            '',
            0,
            0
        );

        return $mail->sendfile();
    }
}
