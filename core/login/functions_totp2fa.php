<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       core/login/functions_totp2fa.php
 * \ingroup    totp2fa
 * \brief      Login functions for TOTP 2FA
 *
 * This file is automatically loaded by Dolibarr's authentication system
 * if it exists in a module's core/login/ directory
 */

/**
 * Check if user has 2FA enabled and needs 2FA verification
 * This function is called after successful password authentication
 *
 * @param string $usertotest Username
 * @param string $passwordtotest Password (already verified)
 * @param int $entitytotest Entity ID
 * @param object $authmode Authentication mode object
 * @param DoliDB $db Database handler
 * @return bool|int False if 2FA not needed, -1 if 2FA required (blocks login)
 */
function check_user_password_totp2fa($usertotest, $passwordtotest, $entitytotest, $authmode, $db)
{
    global $conf, $langs;

    // Skip if module not enabled
    if (empty($conf->totp2fa) || empty($conf->totp2fa->enabled)) {
        return false;
    }

    // Skip if already in 2FA verification process
    if (defined('TOTP2FA_VERIFICATION')) {
        return false;
    }

    // Load user 2FA settings
    dol_include_once('/totp2fa/class/user2fa.class.php');

    // Get user ID
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user";
    $sql .= " WHERE login = '".$db->escape($usertotest)."'";
    $sql .= " AND entity IN (".$db->sanitize($entitytotest).")";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $user_id = $obj->rowid;

            // Check if user has 2FA enabled
            $user2fa = new User2FA($db);
            $result = $user2fa->fetch($user_id);

            if ($result > 0 && $user2fa->is_enabled) {
                // User has 2FA enabled - block login and redirect to 2FA page
                $_SESSION['totp2fa_pending_login'] = 1;
                $_SESSION['totp2fa_user_id'] = $user_id;

                // Store intended URL
                if (!empty($_POST['urlfrom'])) {
                    $_SESSION['totp2fa_urltogo'] = $_POST['urlfrom'];
                } elseif (!empty($_GET['urlfrom'])) {
                    $_SESSION['totp2fa_urltogo'] = $_GET['urlfrom'];
                }

                // Redirect to 2FA verification page
                $langs->load("totp2fa@totp2fa");
                header('Location: '.dol_buildpath('/totp2fa/login_2fa.php', 1));
                exit;
            }
        }
    }

    // No 2FA required, proceed with normal login
    return false;
}
