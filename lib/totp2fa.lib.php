<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       lib/totp2fa.lib.php
 * \ingroup    totp2fa
 * \brief      Helper functions for TOTP 2FA
 */

/**
 * Prepare admin pages header tabs
 *
 * @return array Array of tabs
 */
function totp2fa_admin_prepare_head()
{
    global $langs, $conf;

    $langs->load("totp2fa@totp2fa");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/totp2fa/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'totp2fa');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'totp2fa', 'remove');

    return $head;
}

/**
 * Check if user has 2FA enabled
 *
 * @param DoliDB $db Database handler
 * @param int $fk_user User ID
 * @return bool True if enabled
 */
function totp2fa_is_enabled_for_user($db, $fk_user)
{
    $sql = "SELECT is_enabled FROM ".MAIN_DB_PREFIX."totp2fa_user_settings";
    $sql .= " WHERE fk_user = ".(int)$fk_user;

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            return ($obj->is_enabled == 1);
        }
    }

    return false;
}

/**
 * Get 2FA statistics
 *
 * @param DoliDB $db Database handler
 * @return array Array with statistics
 */
function totp2fa_get_stats($db)
{
    $stats = array(
        'total_users' => 0,
        'users_with_2fa' => 0,
        'percentage' => 0,
    );

    // Total users
    $sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."user WHERE entity IN (".getEntity('user').")";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['total_users'] = $obj->total;
    }

    // Users with 2FA enabled
    $sql = "SELECT COUNT(DISTINCT fk_user) as total FROM ".MAIN_DB_PREFIX."totp2fa_user_settings WHERE is_enabled = 1";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['users_with_2fa'] = $obj->total;
    }

    // Calculate percentage
    if ($stats['total_users'] > 0) {
        $stats['percentage'] = round(($stats['users_with_2fa'] / $stats['total_users']) * 100, 1);
    }

    return $stats;
}
