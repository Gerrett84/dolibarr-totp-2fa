<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/setup.php
 * \ingroup    totp2fa
 * \brief      Admin configuration page for TOTP 2FA module
 */

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/totp2fa/class/user2fa.class.php');

// Security check
if (!$user->admin) {
    accessforbidden();
}

// Load translations
$langs->loadLangs(array("admin", "totp2fa@totp2fa"));

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

if ($action == 'set_TOTP2FA_ENFORCE_ALL') {
    $result = dolibarr_set_const($db, "TOTP2FA_ENFORCE_ALL", GETPOST('value', 'int'), 'chaine', 0, '', $conf->entity);
    if ($result > 0) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

if ($action == 'set_TOTP2FA_ALLOW_SELF_DISABLE') {
    $result = dolibarr_set_const($db, "TOTP2FA_ENFORCE_ALL", GETPOST('value', 'int'), 'chaine', 0, '', $conf->entity);
    if ($result > 0) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

// Trusted Device settings
if ($action == 'set_trusted_device') {
    $enabled = GETPOST('trusted_enabled', 'int');
    $days = GETPOST('trusted_days', 'int');

    // Validate days (1-90)
    if ($days < 1) $days = 1;
    if ($days > 90) $days = 90;

    dolibarr_set_const($db, "TOTP2FA_TRUSTED_DEVICE_ENABLED", $enabled, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "TOTP2FA_TRUSTED_DEVICE_DAYS", $days, 'chaine', 0, '', $conf->entity);

    // Create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."totp2fa_trusted_devices (
        rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
        fk_user         INTEGER NOT NULL,
        device_hash     VARCHAR(64) NOT NULL,
        device_name     VARCHAR(255),
        ip_address      VARCHAR(45),
        user_agent      VARCHAR(512),
        trusted_until   DATETIME NOT NULL,
        date_creation   DATETIME NOT NULL,
        date_last_use   DATETIME,
        tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_device (fk_user, device_hash),
        KEY idx_user (fk_user),
        KEY idx_trusted_until (trusted_until)
    ) ENGINE=InnoDB";
    $db->query($sql);

    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

// Clear expired trusted devices
if ($action == 'clear_expired') {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices WHERE trusted_until < NOW()";
    $db->query($sql);
    setEventMessages($langs->trans("ExpiredDevicesCleared"), null, 'mesgs');
}

// Clear all trusted devices
if ($action == 'clear_all_trusted') {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $db->query($sql);
    setEventMessages($langs->trans("AllTrustedDevicesCleared"), null, 'mesgs');
}

/*
 * View
 */

$page_name = "TOTP2FASetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = array();
$h = 0;

$head[$h][0] = dol_buildpath('/totp2fa/admin/setup.php', 1);
$head[$h][1] = $langs->trans('Settings');
$head[$h][2] = 'settings';
$h++;

$head[$h][0] = dol_buildpath('/totp2fa/admin/activity_log.php', 1);
$head[$h][1] = $langs->trans('ActivityLog');
$head[$h][2] = 'activitylog';
$h++;

print dol_get_fiche_head($head, 'settings', '', -1, 'fa-shield-alt');

// Get statistics
$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."user WHERE entity IN (".getEntity('user').")";
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
$total_users = $obj->total;

$sql = "SELECT COUNT(DISTINCT fk_user) as total FROM ".MAIN_DB_PREFIX."totp2fa_user_settings WHERE is_enabled = 1";
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
$users_with_2fa = $obj->total;

$percentage = $total_users > 0 ? round(($users_with_2fa / $total_users) * 100, 1) : 0;

// Statistics box
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Statistics").'</td>';
print '<td class="right">'.$langs->trans("Value").'</td>';
print '</tr>';

// Module status
print '<tr class="oddeven">';
print '<td>'.$langs->trans("ModuleIsActive").'</td>';
print '<td class="right">';
print '<span class="badge badge-status4 badge-status">'.$langs->trans("Enabled").'</span>';
print '</td>';
print '</tr>';

// Total users
print '<tr class="oddeven">';
print '<td>'.$langs->trans("TotalUsers").'</td>';
print '<td class="right"><strong>'.$total_users.'</strong></td>';
print '</tr>';

// Users with 2FA
print '<tr class="oddeven">';
print '<td>'.$langs->trans("Users2FAEnabled").'</td>';
print '<td class="right">';
print '<strong>'.$users_with_2fa.'</strong>';
print ' <span style="color: #666;">('.$percentage.'%)</span>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<br>';

// Configuration options
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>';
print '<td class="center">'.$langs->trans("Value").'</td>';
print '</tr>';

// Information message
print '<tr class="oddeven">';
print '<td colspan="2">';
print '<div class="info">';
print $langs->trans("TOTP2FADescription");
print '</div>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<br>';

// Security information
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("SecuritySettings").'</td>';
print '<td class="center">'.$langs->trans("Information").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnhancedSecurity").'</td>';
print '<td>';
print '<ul>';
print '<li>'.$langs->trans("2FABenefit1").'</li>';
print '<li>'.$langs->trans("2FABenefit2").'</li>';
print '<li>'.$langs->trans("2FABenefit3").'</li>';
print '<li>'.$langs->trans("2FABenefit4").'</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("CompatibleApps").'</td>';
print '<td>';
print '<ul>';
print '<li>'.$langs->trans("GoogleAuthenticator").'</li>';
print '<li>'.$langs->trans("ApplePasswords").'</li>';
print '<li>'.$langs->trans("MicrosoftAuthenticator").'</li>';
print '<li>'.$langs->trans("Authy").'</li>';
print '<li>'.$langs->trans("OtherTOTPApps").'</li>';
print '</ul>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<br>';

// Help section
print '<div class="info">';
print '<strong>'.$langs->trans("HowToSetup2FA").'</strong><br>';
print '<ol>';
print '<li>'.$langs->trans("Step1").'</li>';
print '<li>'.$langs->trans("Step2").'</li>';
print '<li>'.$langs->trans("Step3").'</li>';
print '<li>'.$langs->trans("Step4").'</li>';
print '</ol>';
print '<p><strong>'.$langs->trans("WhatIfLosePhone").'</strong><br>';
print $langs->trans("UsePrintedBackupCodes").'<br>';
print $langs->trans("ContactAdministrator").'</p>';
print '</div>';

print '<br>';

// Trusted Device Settings
$trustedEnabled = getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_ENABLED', 0);
$trustedDays = getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_DAYS', 30);

// Count trusted devices
$trustedDevicesCount = 0;
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices WHERE trusted_until > NOW()";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $trustedDevicesCount = $obj->cnt;
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("TrustedDeviceSettings").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td colspan="2">';
print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set_trusted_device">';

print '<table class="nobordernopadding">';

// Enable/Disable
print '<tr>';
print '<td style="padding: 8px 0;"><strong>'.$langs->trans("EnableTrustedDevices").'</strong></td>';
print '<td style="padding: 8px 0;">';
print '<select name="trusted_enabled" class="flat">';
print '<option value="0"'.($trustedEnabled == 0 ? ' selected' : '').'>'.$langs->trans("No").'</option>';
print '<option value="1"'.($trustedEnabled == 1 ? ' selected' : '').'>'.$langs->trans("Yes").'</option>';
print '</select>';
print '</td>';
print '</tr>';

// Days
print '<tr>';
print '<td style="padding: 8px 0;"><strong>'.$langs->trans("TrustDeviceForDays").'</strong></td>';
print '<td style="padding: 8px 0;">';
print '<input type="number" name="trusted_days" value="'.$trustedDays.'" min="1" max="90" class="flat" style="width: 80px;"> ';
print $langs->trans("Days").' <span style="color: #666;">(1-90)</span>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 8px 0;"></td>';
print '<td style="padding: 8px 0;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';
print '</td>';
print '</tr>';

// Description
print '<tr class="oddeven">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>';
print '<div style="color: #666; font-size: 13px;">';
print $langs->trans("TrustedDeviceDescription");
if (empty($langs->tab_translate["TrustedDeviceDescription"])) {
    print 'Wenn aktiviert, können Benutzer ihr Gerät als "vertrauenswürdig" markieren. ';
    print 'Für die eingestellte Anzahl an Tagen wird keine erneute 2FA-Code-Eingabe benötigt.';
}
print '</div>';
print '</td>';
print '</tr>';

// Current trusted devices count
print '<tr class="oddeven">';
print '<td>'.$langs->trans("ActiveTrustedDevices").'</td>';
print '<td>';
print '<strong>'.$trustedDevicesCount.'</strong> ';
if ($trustedDevicesCount > 0) {
    print '<a href="'.$_SERVER["PHP_SELF"].'?action=clear_expired&token='.newToken().'" class="button buttongen" style="margin-left: 10px;" onclick="return confirm(\''.$langs->trans("ConfirmClearExpired").'\');">';
    print $langs->trans("ClearExpired");
    print '</a> ';
    print '<a href="'.$_SERVER["PHP_SELF"].'?action=clear_all_trusted&token='.newToken().'" class="button buttongen" style="margin-left: 5px;" onclick="return confirm(\''.$langs->trans("ConfirmClearAllTrusted").'\');">';
    print $langs->trans("ClearAll");
    print '</a>';
}
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
