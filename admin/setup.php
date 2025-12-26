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

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
