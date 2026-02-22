<?php
/* Copyright (C) 2024-2026 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/ip_management.php
 * \ingroup    totp2fa
 * \brief      IP Management page - Login attempts and IP blacklist
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
dol_include_once('/totp2fa/class/actions_totp2fa.class.php');

// Security check
if (!$user->admin) {
    accessforbidden();
}

// Load translations
$langs->loadLangs(array("admin", "totp2fa@totp2fa"));

// Parameters
$action = GETPOST('action', 'aZ09');
$ip_to_block = GETPOST('ip', 'alpha');
$reason = GETPOST('reason', 'alpha');
$days = GETPOST('days', 'int');
$search_ip = GETPOST('search_ip', 'alpha');
$search_type = GETPOST('search_type', 'alpha');
$tab = GETPOST('tab', 'alpha') ? GETPOST('tab', 'alpha') : 'attempts';
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : 50;
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 0;
$offset = $limit * $page;

// Actions
$actions = new ActionsTotp2fa($db);

if ($action == 'block' && !empty($ip_to_block)) {
    $result = $actions->blockIP($ip_to_block, $reason, $user->id, $days);
    if ($result > 0) {
        setEventMessages($langs->trans("IPBlocked").': '.$ip_to_block, null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=blacklist");
    exit;
}

if ($action == 'unblock' && !empty($ip_to_block)) {
    $result = $actions->unblockIP($ip_to_block);
    if ($result > 0) {
        setEventMessages($langs->trans("IPUnblocked").': '.$ip_to_block, null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=blacklist");
    exit;
}

if ($action == 'delete' && !empty($ip_to_block)) {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_ip_blacklist";
    $sql .= " WHERE ip_address = '".$db->escape($ip_to_block)."'";
    $sql .= " AND entity = ".(int)$conf->entity;
    $db->query($sql);
    setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=blacklist");
    exit;
}

if ($action == 'purge_old') {
    $days_to_keep = GETPOST('purge_days', 'int') ? GETPOST('purge_days', 'int') : 30;
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts";
    $sql .= " WHERE datec < DATE_SUB(NOW(), INTERVAL ".(int)$days_to_keep." DAY)";
    $sql .= " AND entity = ".(int)$conf->entity;
    $db->query($sql);
    $deleted = $db->affected_rows;
    setEventMessages($langs->trans("RecordsDeleted", $deleted), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]."?tab=attempts");
    exit;
}

/*
 * View
 */

$page_name = "IPManagement";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("TOTP2FASetup"), $linkback, 'title_setup');

// Configuration header tabs
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

$head[$h][0] = dol_buildpath('/totp2fa/admin/ip_management.php', 1);
$head[$h][1] = $langs->trans('IPManagement');
$head[$h][2] = 'ipmanagement';
$h++;

print dol_get_fiche_head($head, 'ipmanagement', '', -1, 'fa-shield-alt');

// Sub-tabs for IP Management
print '<div class="tabs" style="margin-bottom: 15px;">';
$tabClass1 = ($tab == 'attempts') ? 'tabactive' : 'tabunactive';
$tabClass2 = ($tab == 'blacklist') ? 'tabactive' : 'tabunactive';
$tabClass3 = ($tab == 'stats') ? 'tabactive' : 'tabunactive';
print '<a class="tab '.$tabClass1.'" href="'.$_SERVER["PHP_SELF"].'?tab=attempts">'.$langs->trans("LoginAttempts").'</a>';
print '<a class="tab '.$tabClass2.'" href="'.$_SERVER["PHP_SELF"].'?tab=blacklist">'.$langs->trans("IPBlacklist").'</a>';
print '<a class="tab '.$tabClass3.'" href="'.$_SERVER["PHP_SELF"].'?tab=stats">'.$langs->trans("Statistics").'</a>';
print '</div>';

// TAB: Login Attempts
if ($tab == 'attempts') {
    // Filter form
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="tab" value="attempts">';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Filters").'</td>';
    print '<td class="right">';
    print '<input type="submit" class="button" value="'.$langs->trans("Search").'">';
    print ' <a class="button" href="'.$_SERVER["PHP_SELF"].'?tab=attempts">'.$langs->trans("Reset").'</a>';
    print '</td>';
    print '</tr>';
    print '<tr class="oddeven">';
    print '<td colspan="2">';

    // IP filter
    print $langs->trans("IPAddress").': ';
    print '<input type="text" name="search_ip" value="'.dol_escape_htmltag($search_ip).'" size="20">';
    print '&nbsp;&nbsp;';

    // Type filter
    print $langs->trans("Type").': ';
    print '<select name="search_type" class="flat">';
    print '<option value="">-- '.$langs->trans("All").' --</option>';
    print '<option value="success"'.($search_type == 'success' ? ' selected' : '').'>'.$langs->trans("LoginSuccess").'</option>';
    print '<option value="failed_2fa"'.($search_type == 'failed_2fa' ? ' selected' : '').'>'.$langs->trans("Failed2FA").'</option>';
    print '<option value="failed_password"'.($search_type == 'failed_password' ? ' selected' : '').'>'.$langs->trans("FailedPassword").'</option>';
    print '<option value="blocked"'.($search_type == 'blocked' ? ' selected' : '').'>'.$langs->trans("BlockedAttempt").'</option>';
    print '</select>';

    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
    print '</form>';

    // Purge button
    print '<div style="margin: 10px 0;">';
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display: inline;">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="purge_old">';
    print '<input type="hidden" name="tab" value="attempts">';
    print $langs->trans("PurgeOlderThan").': ';
    print '<select name="purge_days" class="flat">';
    print '<option value="7">7 '.$langs->trans("Days").'</option>';
    print '<option value="30" selected>30 '.$langs->trans("Days").'</option>';
    print '<option value="90">90 '.$langs->trans("Days").'</option>';
    print '</select>';
    print ' <input type="submit" class="button" value="'.$langs->trans("Purge").'" onclick="return confirm(\''.$langs->trans("ConfirmPurge").'\');">';
    print '</form>';
    print '</div>';

    // Build query
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts";
    $sql .= " WHERE entity = ".(int)$conf->entity;
    if (!empty($search_ip)) {
        $sql .= " AND ip_address LIKE '%".$db->escape($search_ip)."%'";
    }
    if (!empty($search_type)) {
        $sql .= " AND attempt_type = '".$db->escape($search_type)."'";
    }
    $sql .= " ORDER BY datec DESC";
    $sql .= " LIMIT ".(int)$offset.", ".(int)$limit;

    $resql = $db->query($sql);

    // Login attempts table
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Date").'</td>';
    print '<td>'.$langs->trans("IPAddress").'</td>';
    print '<td>'.$langs->trans("Username").'</td>';
    print '<td>'.$langs->trans("Type").'</td>';
    print '<td>'.$langs->trans("Browser").'</td>';
    print '<td>'.$langs->trans("Actions").'</td>';
    print '</tr>';

    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';

            // Date
            print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';

            // IP
            print '<td><strong>'.$obj->ip_address.'</strong></td>';

            // Username
            print '<td>'.$obj->username.'</td>';

            // Type with badge
            print '<td>';
            switch ($obj->attempt_type) {
                case 'success':
                    print '<span class="badge badge-status4">'.$langs->trans("LoginSuccess").'</span>';
                    break;
                case 'failed_2fa':
                    print '<span class="badge badge-status8">'.$langs->trans("Failed2FA").'</span>';
                    break;
                case 'failed_password':
                    print '<span class="badge badge-status1">'.$langs->trans("FailedPassword").'</span>';
                    break;
                case 'blocked':
                    print '<span class="badge badge-status8">'.$langs->trans("BlockedAttempt").'</span>';
                    break;
                default:
                    print $obj->attempt_type;
            }
            print '</td>';

            // Browser (shortened)
            $browser = $obj->user_agent;
            if (strlen($browser) > 50) {
                $browser = substr($browser, 0, 50).'...';
            }
            print '<td title="'.dol_escape_htmltag($obj->user_agent).'">'.$browser.'</td>';

            // Actions
            print '<td>';
            print '<a class="button buttonDelete" href="'.$_SERVER["PHP_SELF"].'?action=block&ip='.urlencode($obj->ip_address).'&tab=attempts&token='.newToken().'" onclick="return confirm(\''.$langs->trans("ConfirmBlockIP").'\');" title="'.$langs->trans("BlockIP").'">';
            print '<span class="fa fa-ban"></span>';
            print '</a>';
            print '</td>';

            print '</tr>';
        }
    } else {
        print '<tr class="oddeven">';
        print '<td colspan="6" class="center">'.$langs->trans("NoRecordsFound").'</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

// TAB: IP Blacklist
if ($tab == 'blacklist') {
    // Add new block form
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="block">';
    print '<input type="hidden" name="tab" value="blacklist">';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td colspan="4">'.$langs->trans("BlockNewIP").'</td>';
    print '</tr>';
    print '<tr class="oddeven">';
    print '<td>'.$langs->trans("IPAddress").':<br><input type="text" name="ip" required placeholder="192.168.1.100" size="20"></td>';
    print '<td>'.$langs->trans("Reason").':<br><input type="text" name="reason" placeholder="'.$langs->trans("Optional").'" size="30"></td>';
    print '<td>'.$langs->trans("Duration").':<br>';
    print '<select name="days" class="flat">';
    print '<option value="0">'.$langs->trans("Permanent").'</option>';
    print '<option value="1">1 '.$langs->trans("Day").'</option>';
    print '<option value="7">7 '.$langs->trans("Days").'</option>';
    print '<option value="30">30 '.$langs->trans("Days").'</option>';
    print '<option value="90">90 '.$langs->trans("Days").'</option>';
    print '</select>';
    print '</td>';
    print '<td><br><input type="submit" class="button" value="'.$langs->trans("Block").'"></td>';
    print '</tr>';
    print '</table>';
    print '</div>';
    print '</form>';

    print '<br>';

    // Blacklist table
    $sql = "SELECT b.*, u.login as blocked_by_login FROM ".MAIN_DB_PREFIX."totp2fa_ip_blacklist b";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON b.blocked_by = u.rowid";
    $sql .= " WHERE b.entity = ".(int)$conf->entity;
    $sql .= " ORDER BY b.datec DESC";

    $resql = $db->query($sql);

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("IPAddress").'</td>';
    print '<td>'.$langs->trans("Reason").'</td>';
    print '<td>'.$langs->trans("BlockedBy").'</td>';
    print '<td>'.$langs->trans("BlockedOn").'</td>';
    print '<td>'.$langs->trans("ExpiresOn").'</td>';
    print '<td>'.$langs->trans("Status").'</td>';
    print '<td>'.$langs->trans("Actions").'</td>';
    print '</tr>';

    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';

            // IP
            print '<td><strong>'.$obj->ip_address.'</strong></td>';

            // Reason
            print '<td>'.$obj->reason.'</td>';

            // Blocked by
            print '<td>'.$obj->blocked_by_login.'</td>';

            // Date blocked
            print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';

            // Expiry
            print '<td>';
            if (!empty($obj->date_expiry)) {
                print dol_print_date($db->jdate($obj->date_expiry), 'dayhour');
            } else {
                print '<em>'.$langs->trans("Permanent").'</em>';
            }
            print '</td>';

            // Status
            print '<td>';
            if ($obj->active) {
                print '<span class="badge badge-status8">'.$langs->trans("Active").'</span>';
            } else {
                print '<span class="badge badge-status0">'.$langs->trans("Inactive").'</span>';
            }
            print '</td>';

            // Actions
            print '<td>';
            if ($obj->active) {
                print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=unblock&ip='.urlencode($obj->ip_address).'&tab=blacklist&token='.newToken().'" title="'.$langs->trans("Unblock").'">';
                print '<span class="fa fa-unlock"></span>';
                print '</a> ';
            }
            print '<a class="button buttonDelete" href="'.$_SERVER["PHP_SELF"].'?action=delete&ip='.urlencode($obj->ip_address).'&tab=blacklist&token='.newToken().'" onclick="return confirm(\''.$langs->trans("ConfirmDelete").'\');" title="'.$langs->trans("Delete").'">';
            print '<span class="fa fa-trash"></span>';
            print '</a>';
            print '</td>';

            print '</tr>';
        }
    } else {
        print '<tr class="oddeven">';
        print '<td colspan="7" class="center">'.$langs->trans("NoBlockedIPs").'</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

// TAB: Statistics
if ($tab == 'stats') {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td colspan="2">'.$langs->trans("LoginStatistics").'</td>';
    print '</tr>';

    // Total attempts today
    $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts WHERE entity = ".(int)$conf->entity." AND DATE(datec) = CURDATE()";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    print '<tr class="oddeven"><td>'.$langs->trans("AttemptsToday").'</td><td><strong>'.$obj->cnt.'</strong></td></tr>';

    // Successful logins today
    $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts WHERE entity = ".(int)$conf->entity." AND attempt_type = 'success' AND DATE(datec) = CURDATE()";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    print '<tr class="oddeven"><td>'.$langs->trans("SuccessfulLoginsToday").'</td><td><span class="badge badge-status4">'.$obj->cnt.'</span></td></tr>';

    // Failed 2FA today
    $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts WHERE entity = ".(int)$conf->entity." AND attempt_type = 'failed_2fa' AND DATE(datec) = CURDATE()";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    print '<tr class="oddeven"><td>'.$langs->trans("Failed2FAToday").'</td><td><span class="badge badge-status8">'.$obj->cnt.'</span></td></tr>';

    // Blocked attempts today
    $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts WHERE entity = ".(int)$conf->entity." AND attempt_type = 'blocked' AND DATE(datec) = CURDATE()";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    print '<tr class="oddeven"><td>'.$langs->trans("BlockedAttemptsToday").'</td><td><span class="badge badge-status8">'.$obj->cnt.'</span></td></tr>';

    // Active blocked IPs
    $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_ip_blacklist WHERE entity = ".(int)$conf->entity." AND active = 1 AND (date_expiry IS NULL OR date_expiry > NOW())";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    print '<tr class="oddeven"><td>'.$langs->trans("ActiveBlockedIPs").'</td><td><strong>'.$obj->cnt.'</strong></td></tr>';

    print '</table>';
    print '</div>';

    // Top IPs with failed attempts (last 7 days)
    print '<br>';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td colspan="3">'.$langs->trans("TopFailedIPs7Days").'</td>';
    print '</tr>';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("IPAddress").'</td>';
    print '<td>'.$langs->trans("FailedAttempts").'</td>';
    print '<td>'.$langs->trans("Actions").'</td>';
    print '</tr>';

    $sql = "SELECT ip_address, COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_login_attempts";
    $sql .= " WHERE entity = ".(int)$conf->entity;
    $sql .= " AND attempt_type IN ('failed_2fa', 'failed_password', 'blocked')";
    $sql .= " AND datec > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $sql .= " GROUP BY ip_address ORDER BY cnt DESC LIMIT 10";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        while ($obj = $db->fetch_object($resql)) {
            print '<tr class="oddeven">';
            print '<td><strong>'.$obj->ip_address.'</strong></td>';
            print '<td><span class="badge badge-status8">'.$obj->cnt.'</span></td>';
            print '<td>';
            print '<a class="button buttonDelete" href="'.$_SERVER["PHP_SELF"].'?action=block&ip='.urlencode($obj->ip_address).'&reason='.urlencode($langs->trans("MultipleFailedAttempts")).'&tab=blacklist&token='.newToken().'" title="'.$langs->trans("BlockIP").'">';
            print '<span class="fa fa-ban"></span> '.$langs->trans("Block");
            print '</a>';
            print '</td>';
            print '</tr>';
        }
    } else {
        print '<tr class="oddeven"><td colspan="3" class="center">'.$langs->trans("NoFailedAttempts").'</td></tr>';
    }

    print '</table>';
    print '</div>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
