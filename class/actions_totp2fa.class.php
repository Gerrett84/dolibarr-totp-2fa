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
     * Execute action on main page
     * This is called on almost every page
     *
     * @param array         $parameters Parameters
     * @param CommonObject  $object     Object
     * @param string        $action     Action name
     * @param HookManager   $hookmanager Hook manager
     * @return int 0 if OK, <0 if KO
     */
    public function main($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user;

        // Skip if module not enabled
        if (empty($conf->totp2fa->enabled)) {
            return 0;
        }

        // Skip if already verified
        if (!empty($_SESSION['totp2fa_verified']) && $_SESSION['totp2fa_verified'] == $user->id) {
            return 0;
        }

        // Check if user has 2FA and redirect if needed
        // This is now handled by the login page directly

        return 0;
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
        if (empty($conf->totp2fa->enabled)) {
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
        if (empty($conf->totp2fa->enabled)) {
            return 0;
        }

        $usertotest = isset($parameters['usertotest']) ? $parameters['usertotest'] : GETPOST('username', 'alpha');
        $totp_code = GETPOST('totp_code', 'alpha');

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
            // User has 2FA enabled

            if (empty($totp_code)) {
                // No 2FA code provided - block login
                $langs->load("totp2fa@totp2fa");
                $this->errors[] = $langs->trans("PleaseEnterCode");
                return -1; // Block login
            }

            // Verify the 2FA code
            $isValid = $user2fa->verifyCode($totp_code);

            if (!$isValid) {
                // Invalid code - block login
                $langs->load("totp2fa@totp2fa");
                $this->errors[] = $user2fa->error ? $user2fa->error : $langs->trans("InvalidCode");
                return -1; // Block login
            }

            // Code is valid - allow login and mark as verified
            $_SESSION['totp2fa_verified'] = $user_id;
        }

        return 0; // Allow login
    }
}
