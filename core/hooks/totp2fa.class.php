<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       core/hooks/totp2fa.class.php
 * \ingroup    totp2fa
 * \brief      Hook file for TOTP 2FA
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
     * @var string Return value
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
     * Execute action after login
     * Called on every page load after successful login
     *
     * @param array         $parameters Parameters
     * @param CommonObject  $object     Object
     * @param string        $action     Action name
     * @param HookManager   $hookmanager Hook manager
     * @return int 0 if OK, <0 if KO
     */
    public function afterLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        // Skip if module not enabled
        if (empty($conf->totp2fa->enabled)) {
            return 0;
        }

        // Skip if already in 2FA verification process
        if (!empty($_SESSION['totp2fa_pending_login'])) {
            return 0;
        }

        // Skip if we're on the 2FA page itself
        if (strpos($_SERVER['PHP_SELF'], 'login_2fa.php') !== false) {
            return 0;
        }

        // Skip if we're on the login page
        if (strpos($_SERVER['PHP_SELF'], 'index.php') !== false && empty($user->id)) {
            return 0;
        }

        // Skip if user not logged in
        if (empty($user->id)) {
            return 0;
        }

        // Check if we already verified 2FA in this session
        if (!empty($_SESSION['totp2fa_verified']) && $_SESSION['totp2fa_verified'] == $user->id) {
            return 0;
        }

        // Check if user has 2FA enabled
        $user2fa = new User2FA($this->db);
        $result = $user2fa->fetch($user->id);

        if ($result > 0 && $user2fa->is_enabled) {
            // User has 2FA enabled but hasn't verified in this session
            dol_syslog("TOTP2FA: User ".$user->id." has 2FA enabled, redirecting to verification");

            $_SESSION['totp2fa_pending_login'] = 1;
            $_SESSION['totp2fa_user_id'] = $user->id;

            // Store intended URL
            $urltogo = $_SERVER['REQUEST_URI'];
            if (!empty($urltogo) && strpos($urltogo, 'login_2fa.php') === false) {
                $_SESSION['totp2fa_urltogo'] = $urltogo;
            }

            // Redirect to 2FA verification
            header('Location: '.dol_buildpath('/custom/totp2fa/login_2fa.php', 1));
            exit;
        }

        return 0;
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
        // Use the same logic as afterLogin
        return $this->afterLogin($parameters, $object, $action, $hookmanager);
    }
}
