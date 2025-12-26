<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/activity_log.php
 * \ingroup    totp2fa
 * \brief      Activity log page for TOTP 2FA module
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/totp2fa/class/totp2fa_activity.class.php');

// Security check
if (!$user->admin) {
    accessforbidden();
}

// Load translations
$langs->loadLangs(array("admin", "totp2fa@totp2fa"));

// Parameters
$action = GETPOST('action', 'aZ09');
$search_user = GETPOST('search_user', 'int');
$search_action = GETPOST('search_action', 'alpha');
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : 50;
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 0;
$offset = $limit * $page;

/*
 * View
 */

$page_name = "ActivityLog";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("TOTP2FASetup"), $linkback, 'title_setup');

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

print dol_get_fiche_head($head, 'activitylog', '', -1, 'fa-shield-alt');

// Initialize activity class
$activity = new Totp2faActivity($db);

// Get logs
$logs = $activity->getLog($search_user, $limit, $offset);

// Count total
$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."totp2fa_activity_log WHERE entity = ".((int) $conf->entity);
if ($search_user > 0) {
    $sql .= " AND fk_user = ".((int) $search_user);
}
$resql = $db->query($sql);
$obj = $db->fetch_object($resql);
$total_records = $obj ? $obj->total : 0;

// Filter form
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Filters").'</td>';
print '<td class="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Search").'">';
print ' <a class="button" href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("Reset").'</a>';
print '</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td colspan="2">';

// User filter
print $langs->trans("User").': ';
print '<select name="search_user" class="flat">';
print '<option value="">-- '.$langs->trans("All").' --</option>';

$sql = "SELECT DISTINCT u.rowid, u.login, u.firstname, u.lastname";
$sql .= " FROM ".MAIN_DB_PREFIX."totp2fa_activity_log as l";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON l.fk_user = u.rowid";
$sql .= " WHERE l.entity = ".((int) $conf->entity);
$resql = $db->query($sql);
while ($obj = $db->fetch_object($resql)) {
    $selected = ($search_user == $obj->rowid) ? ' selected' : '';
    print '<option value="'.$obj->rowid.'"'.$selected.'>'.$obj->login.' ('.trim($obj->firstname.' '.$obj->lastname).')</option>';
}
print '</select>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

print '<br>';

// Activity log table
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Date").'</td>';
print '<td>'.$langs->trans("User").'</td>';
print '<td>'.$langs->trans("Action").'</td>';
print '<td>'.$langs->trans("IPAddress").'</td>';
print '<td>'.$langs->trans("Details").'</td>';
print '</tr>';

if (count($logs) > 0) {
    foreach ($logs as $log) {
        print '<tr class="oddeven">';

        // Date
        print '<td>'.dol_print_date($log['date'], 'dayhour').'</td>';

        // User
        print '<td>';
        if (!empty($log['user_login'])) {
            print '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$log['user_id'].'">';
            print img_picto('', 'user', 'class="paddingright"');
            print $log['user_login'];
            if (!empty($log['user_name'])) {
                print ' <span style="color:#666;">('.$log['user_name'].')</span>';
            }
            print '</a>';
        } else {
            print $log['user_id'];
        }
        print '</td>';

        // Action with color coding
        print '<td>';
        $actionLabel = Totp2faActivity::getActionLabel($log['action']);
        $actionClass = '';
        switch ($log['action']) {
            case Totp2faActivity::ACTION_2FA_ENABLED:
                $actionClass = 'badge badge-status4';
                break;
            case Totp2faActivity::ACTION_2FA_DISABLED:
                $actionClass = 'badge badge-status1';
                break;
            case Totp2faActivity::ACTION_LOGIN_SUCCESS:
                $actionClass = 'badge badge-status4';
                break;
            case Totp2faActivity::ACTION_LOGIN_FAILED:
                $actionClass = 'badge badge-status8';
                break;
            case Totp2faActivity::ACTION_BACKUP_CODE_USED:
                $actionClass = 'badge badge-status6';
                break;
            case Totp2faActivity::ACTION_SECRET_REGENERATED:
                $actionClass = 'badge badge-status5';
                break;
            default:
                $actionClass = 'badge badge-status0';
        }
        print '<span class="'.$actionClass.'">'.$actionLabel.'</span>';
        print '</td>';

        // IP Address
        print '<td>'.$log['ip_address'].'</td>';

        // Details
        print '<td>'.$log['details'].'</td>';

        print '</tr>';
    }
} else {
    print '<tr class="oddeven">';
    print '<td colspan="5" class="center">'.$langs->trans("NoActivityLog").'</td>';
    print '</tr>';
}

print '</table>';
print '</div>';

// Pagination
if ($total_records > $limit) {
    print '<div class="center" style="margin-top: 10px;">';
    $num_pages = ceil($total_records / $limit);
    for ($i = 0; $i < $num_pages; $i++) {
        $class = ($i == $page) ? 'butActionRefused' : 'butAction';
        print '<a class="'.$class.'" href="'.$_SERVER["PHP_SELF"].'?page='.$i.'&limit='.$limit;
        if ($search_user > 0) {
            print '&search_user='.$search_user;
        }
        print '">'.($i + 1).'</a> ';
    }
    print '</div>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
