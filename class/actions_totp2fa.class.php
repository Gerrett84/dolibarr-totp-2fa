<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/actions_totp2fa.class.php
 * \ingroup    totp2fa
 * \brief      Hook actions file for TOTP 2FA
 */

dol_include_once('/totp2fa/class/user2fa.class.php');
dol_include_once('/totp2fa/lib/totp2fa.lib.php');

/**
 * TOTP 2FA Hook class
 */
class ActionsTotp2fa
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var int Results
     */
    public $results;

    /**
     * @var string Return value for hook output (used by HookManager)
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Add content to login page
     * Hook: getLoginPageExtraContent (called AFTER </html> tag)
     *
     * @param array         $parameters Parameters
     * @param CommonObject  $object     Object
     * @param string        $action     Action name
     * @param HookManager   $hookmanager Hook manager
     * @return int 0 if OK, <0 if KO
     */
    public function getLoginPageExtraContent($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        // Only run if module is enabled
        if (!isModEnabled('totp2fa')) {
            return 0;
        }

        // Capture output from login extension script (JavaScript for 2FA field)
        ob_start();
        include dol_buildpath('/custom/totp2fa/login_extension.php', 0);
        $this->resprints = ob_get_clean();

        return 0;
    }

    /**
     * Check 2FA code before login completes
     * Hook: beforeLoginAuthentication
     *
     * @param array         $parameters Parameters (contains usertotest, entitytotest)
     * @param CommonObject  $object     Object
     * @param string        $action     Action name
     * @param HookManager   $hookmanager Hook manager
     * @return int 0 if OK, <0 to block login
     */
    public function beforeLoginAuthentication($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $db, $langs;

        // Only run if module is enabled
        if (!isModEnabled('totp2fa')) {
            return 0;
        }

        $usertotest = isset($parameters['usertotest']) ? $parameters['usertotest'] : GETPOST('username', 'alpha');
        $totp_code = GETPOST('totp_code', 'alpha');
        $ip_address = $this->getClientIP();

        // Check if IP is blocked
        if ($this->isIpBlocked($ip_address)) {
            $this->logLoginAttempt($ip_address, $usertotest, 'blocked');
            $langs->load("totp2fa@totp2fa");
            $this->errors[] = $langs->trans("IPBlocked");
            return -1;
        }

        if (empty($usertotest)) {
            return 0;
        }

        // Get user ID
        $sql = "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user as u";
        $sql .= " WHERE u.login = '".$db->escape($usertotest)."'";
        $sql .= " AND u.entity IN (".getEntity('user').")";

        $resql = $db->query($sql);
        if (!$resql || $db->num_rows($resql) == 0) {
            return 0;
        }

        $obj = $db->fetch_object($resql);
        $user_id = $obj->rowid;

        // Check if user has 2FA enabled
        dol_include_once('/totp2fa/class/user2fa.class.php');

        $user2fa = new User2FA($db);
        $result = $user2fa->fetch($user_id);

        if ($result > 0 && $user2fa->is_enabled) {
            $trustedEnabled = getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_ENABLED', 0);
            $trustedDays = getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_DAYS', 30);

            if (empty($totp_code)) {
                // Trusted browser (random cookie token): no code needed
                if ($trustedEnabled && totp2fa_is_device_trusted($db, $user_id)) {
                    return 0;
                }
                $langs->load("totp2fa@totp2fa");
                $this->errors[] = $langs->trans("PleaseEnterCode");
                return -1;
            }

            // Throttle per IP+user and per user overall
            if ($this->isThrottled($usertotest, $ip_address)) {
                $this->logLoginAttempt($ip_address, $usertotest, 'blocked');
                $GLOBALS['totp2fa_attempt_logged'] = true;
                $langs->load("totp2fa@totp2fa");
                $this->errors[] = $langs->trans("TooManyAttempts");
                return -1;
            }

            // Backup codes contain a dash, TOTP codes are plain digits
            if (strpos($totp_code, '-') !== false) {
                $isValid = $user2fa->verifyBackupCode($totp_code);
            } else {
                $isValid = $user2fa->verifyCode($totp_code);
            }

            if (!$isValid) {
                $user2fa->logLoginFailed();
                $this->logLoginAttempt($ip_address, $usertotest, 'failed_2fa');
                $GLOBALS['totp2fa_attempt_logged'] = true;

                $langs->load("totp2fa@totp2fa");
                $this->errors[] = $user2fa->error ? $user2fa->error : $langs->trans("InvalidCode");
                return -1;
            }

            $user2fa->logLoginSuccess();
            $this->logLoginAttempt($ip_address, $usertotest, 'success');
            $GLOBALS['totp2fa_attempt_logged'] = true;

            // The device is only trusted after the password was verified too (see trigger USER_LOGIN)
            if ($trustedEnabled) {
                $_SESSION['totp2fa_trust_pending'] = (int) $user_id;
            }
        }

        return 0; // Allow login
    }

    /**
     * Too many failed 2FA attempts from this IP for this user (5 / 5 min) or for this user overall (30 / 15 min)?
     *
     * @param string $username   Login
     * @param string $ip_address Client IP
     * @return bool
     */
    private function isThrottled($username, $ip_address)
    {
        $base = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts";
        $base .= " WHERE username = '".$this->db->escape($username)."' AND attempt_type = 'failed_2fa'";

        $res = $this->db->query($base." AND ip_address = '".$this->db->escape($ip_address)."' AND datec > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        if ($res && (int) $this->db->fetch_object($res)->cnt >= 5) {
            return true;
        }
        $res = $this->db->query($base." AND datec > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        if ($res && (int) $this->db->fetch_object($res)->cnt >= 30) {
            return true;
        }
        return false;
    }

    /**
     * Get client IP address (proxy headers only honoured from trusted proxies)
     *
     * @return string
     */
    private function getClientIP()
    {
        return totp2fa_get_client_ip();
    }

    /**
     * Check if IP is blocked (exact match or CIDR range)
     *
     * @param string $ip_address IP
     * @return bool
     */
    private function isIpBlocked($ip_address)
    {
        global $conf;

        $sql = "SELECT ip_address FROM ".MAIN_DB_PREFIX."totp2fa_ip_blacklist";
        $sql .= " WHERE active = 1";
        $sql .= " AND entity = ".(int) $conf->entity;
        $sql .= " AND (date_expiry IS NULL OR date_expiry > NOW())";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                if (totp2fa_ip_matches($ip_address, $obj->ip_address)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Log a login attempt
     *
     * @param string $ip_address IP address
     * @param string $username Username attempted
     * @param string $attempt_type Type: 'success', 'failed_password', 'failed_2fa', 'blocked'
     */
    private function logLoginAttempt($ip_address, $username, $attempt_type)
    {
        global $conf;

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_login_attempts";
        $sql .= " (ip_address, username, user_agent, attempt_type, datec, entity)";
        $sql .= " VALUES (";
        $sql .= "'".$this->db->escape($ip_address)."',";
        $sql .= "'".$this->db->escape($username)."',";
        $sql .= "'".$this->db->escape(substr($user_agent, 0, 500))."',";
        $sql .= "'".$this->db->escape($attempt_type)."',";
        $sql .= "NOW(),";
        $sql .= (int)$conf->entity;
        $sql .= ")";

        $this->db->query($sql);
    }

    /**
     * Block an IP address
     *
     * @param string $ip_address IP to block
     * @param string $reason Reason for blocking
     * @param int $blocked_by User ID who blocked
     * @param int $days Days to block (0 = permanent)
     * @return int >0 if OK, <0 if KO
     */
    public function blockIP($ip_address, $reason = '', $blocked_by = 0, $days = 0)
    {
        global $conf;

        $ip_address = trim($ip_address);
        if (!totp2fa_is_valid_ip_or_range($ip_address)) {
            $this->error = 'Invalid IP address or range';
            return -1;
        }

        // Remove existing entry
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_ip_blacklist";
        $sql .= " WHERE ip_address = '".$this->db->escape($ip_address)."'";
        $sql .= " AND entity = ".(int)$conf->entity;
        $this->db->query($sql);

        // Insert new entry
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_ip_blacklist";
        $sql .= " (ip_address, reason, blocked_by, datec, date_expiry, active, entity)";
        $sql .= " VALUES (";
        $sql .= "'".$this->db->escape($ip_address)."',";
        $sql .= "'".$this->db->escape($reason)."',";
        $sql .= ($blocked_by > 0 ? (int)$blocked_by : "NULL").",";
        $sql .= "NOW(),";
        $sql .= ($days > 0 ? "DATE_ADD(NOW(), INTERVAL ".(int)$days." DAY)" : "NULL").",";
        $sql .= "1,";
        $sql .= (int)$conf->entity;
        $sql .= ")";

        if ($this->db->query($sql)) {
            return 1;
        }
        return -1;
    }

    /**
     * Unblock an IP address
     *
     * @param string $ip_address IP to unblock
     * @return int >0 if OK, <0 if KO
     */
    public function unblockIP($ip_address)
    {
        global $conf;

        $sql = "UPDATE ".MAIN_DB_PREFIX."totp2fa_ip_blacklist";
        $sql .= " SET active = 0";
        $sql .= " WHERE ip_address = '".$this->db->escape($ip_address)."'";
        $sql .= " AND entity = ".(int)$conf->entity;

        if ($this->db->query($sql)) {
            return 1;
        }
        return -1;
    }
}
