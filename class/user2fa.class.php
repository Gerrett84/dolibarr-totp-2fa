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
     * @var string Encryption key
     */
    private $encryptionKey;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->totp = new TOTP();

        // Get encryption key from config (or generate if not exists)
        global $conf;
        if (!empty($conf->global->TOTP2FA_ENCRYPTION_KEY)) {
            $this->encryptionKey = $conf->global->TOTP2FA_ENCRYPTION_KEY;
        } else {
            // For now, use a simple key derivation from database config
            // In production, this should be a proper key management system
            $this->encryptionKey = hash('sha256', $conf->db->name.$conf->db->user, true);
        }
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
        // Check rate limiting (max 5 attempts per minute)
        if ($this->failed_attempts >= 5 && (time() - $this->last_failed_attempt) < 60) {
            $this->error = 'Too many failed attempts. Please wait before trying again.';
            return false;
        }

        // Check if code was already used (within current time window)
        if ($this->last_used_code === $code && (time() - $this->last_used_time) < 30) {
            $this->error = 'This code has already been used.';
            $this->incrementFailedAttempts();
            return false;
        }

        // Decrypt secret
        $secret = $this->decryptSecret($this->secret);

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

        for ($i = 0; $i < $count; $i++) {
            // Generate random 8-digit code
            $code = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            $code = substr($code, 0, 4).'-'.substr($code, 4, 4); // Format: 1234-5678
            $codeHash = hash('sha256', $code);

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
        $codeHash = hash('sha256', $code);

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."totp2fa_backup_codes";
        $sql .= " WHERE fk_user = ".(int)$this->fk_user;
        $sql .= " AND code_hash = '".$this->db->escape($codeHash)."'";
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

            return true;
        }

        return false;
    }

    /**
     * Encrypt secret using AES-256
     *
     * @param string $plaintext Plain secret
     * @return string Encrypted secret (base64)
     */
    private function encryptSecret($plaintext)
    {
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $this->encryptionKey, 0, $iv);

        // Store IV with encrypted data
        return base64_encode($iv.$encrypted);
    }

    /**
     * Decrypt secret
     *
     * @param string $encrypted Encrypted secret (base64)
     * @return string Plain secret
     */
    private function decryptSecret($encrypted)
    {
        $data = base64_decode($encrypted);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
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

        // Check if we need to send a warning email (3 failed attempts)
        $recentFails = $activity->countRecentFailedAttempts($this->fk_user, 5);
        if ($recentFails == 3) {
            $this->sendFailedAttemptsEmail($recentFails);
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

        // Prepare email
        if ($type === 'enabled') {
            $subject = $langs->trans("Email2FAEnabledSubject");
            $body = sprintf($langs->trans("Email2FAEnabledBody"), $userObj->getFullName($langs));
        } else {
            $subject = $langs->trans("Email2FADisabledSubject");
            $body = sprintf($langs->trans("Email2FADisabledBody"), $userObj->getFullName($langs));
        }

        // Add company signature
        $body .= "\n\n".$conf->global->MAIN_INFO_SOCIETE_NOM;

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

        // Prepare email
        $subject = $langs->trans("Email2FAFailedAttemptsSubject");
        $body = sprintf(
            $langs->trans("Email2FAFailedAttemptsBody"),
            $userObj->getFullName($langs),
            $attempts,
            $ip,
            $time
        );

        // Add company signature
        $body .= "\n\n".$conf->global->MAIN_INFO_SOCIETE_NOM;

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
