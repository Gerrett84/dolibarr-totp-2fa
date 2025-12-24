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
}
