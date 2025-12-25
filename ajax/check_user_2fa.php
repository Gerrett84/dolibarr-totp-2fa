<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       ajax/check_user_2fa.php
 * \ingroup    totp2fa
 * \brief      AJAX endpoint to check if user has 2FA enabled
 */

// CRITICAL: Must allow access without being logged in!
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1'); // No CSRF check for this AJAX endpoint
}

// Set JSON header BEFORE loading main.inc.php
header('Content-Type: application/json');
header('X-Debug-Endpoint: check_user_2fa.php');
header('X-Debug-Time: ' . date('Y-m-d H:i:s'));

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

dol_include_once('/totp2fa/class/user2fa.class.php');

$username = GETPOST('username', 'alpha');
$has_2fa = false;

if (!empty($username)) {
    // Get user ID by username
    $sql = "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user as u";
    $sql .= " WHERE u.login = '".$db->escape($username)."'";
    $sql .= " AND u.entity IN (".getEntity('user').")";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $user_id = $obj->rowid;

        // Check if this user has 2FA enabled
        $user2fa = new User2FA($db);
        $result = $user2fa->fetch($user_id);

        if ($result > 0 && $user2fa->is_enabled) {
            $has_2fa = true;
        }
    }
}

// Return JSON response and exit immediately
echo json_encode(array('has_2fa' => $has_2fa));
exit;
