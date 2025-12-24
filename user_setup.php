<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       user_setup.php
 * \ingroup    totp2fa
 * \brief      User 2FA setup page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/totp2fa/class/user2fa.class.php');
dol_include_once('/totp2fa/class/totp.class.php');
dol_include_once('/totp2fa/lib/qrcode.lib.php');

// Initialize form object
$form = new Form($db);

// Load translations
$langs->loadLangs(array("users", "totp2fa@totp2fa"));

// Security check - user must be logged in
if (!$user->rights->user->self->creer && !$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$code = GETPOST('code', 'alpha');
$backupcode = GETPOST('backupcode', 'alpha');
$id = GETPOST('id', 'int') ? GETPOST('id', 'int') : $user->id;

// Load user
$object = new User($db);
$object->fetch($id);

// Security check - user can only manage their own 2FA (unless admin)
if ($object->id != $user->id && !$user->admin) {
    accessforbidden();
}

// Load user 2FA settings
$user2fa = new User2FA($db);
$user2fa->fk_user = $object->id;
$result = $user2fa->fetch($object->id);

/*
 * Actions
 */

// Enable 2FA - Generate secret
if ($action == 'enable_2fa' && !$user2fa->is_enabled) {
    dol_syslog("TOTP2FA: enable_2fa action triggered for user ".$object->id);
    if ($result <= 0) {
        // No existing settings, create new
        dol_syslog("TOTP2FA: Creating new 2FA settings");
        $user2fa->fk_user = $object->id;
        $result = $user2fa->create($user);
        dol_syslog("TOTP2FA: Create result: ".$result);
        if ($result > 0) {
            $action = 'setup'; // Show QR code
            dol_syslog("TOTP2FA: Setting action to setup");
        } else {
            setEventMessages($langs->trans("ErrorGeneratingSecret").": ".$user2fa->error, null, 'errors');
        }
    } else {
        // Settings exist but disabled, regenerate secret
        dol_syslog("TOTP2FA: Deleting and recreating 2FA settings");
        $user2fa->delete($user);
        $user2fa = new User2FA($db);
        $user2fa->fk_user = $object->id;
        $result = $user2fa->create($user);
        dol_syslog("TOTP2FA: Create result: ".$result);
        if ($result > 0) {
            $action = 'setup';
            dol_syslog("TOTP2FA: Setting action to setup");
        } else {
            setEventMessages($langs->trans("ErrorGeneratingSecret").": ".$user2fa->error, null, 'errors');
        }
    }
}

// Verify code and activate 2FA
if ($action == 'verify' && !empty($code)) {
    if ($user2fa->id > 0) {
        $isValid = $user2fa->verifyCode($code);
        if ($isValid) {
            // Enable 2FA
            $user2fa->is_enabled = 1;
            $user2fa->update($user);

            // Generate backup codes
            $backupCodes = $user2fa->generateBackupCodes(10);
            $_SESSION['totp2fa_backup_codes'] = $backupCodes; // Store temporarily for display

            setEventMessages($langs->trans("2FAEnabledSuccess"), null, 'mesgs');
            $action = 'backup_codes';
        } else {
            setEventMessages($user2fa->error ? $user2fa->error : $langs->trans("InvalidCode"), null, 'errors');
            $action = 'setup';
        }
    }
}

// Disable 2FA
if ($action == 'confirm_disable' && $confirm == 'yes') {
    if ($user2fa->id > 0) {
        $result = $user2fa->delete($user);
        if ($result > 0) {
            setEventMessages($langs->trans("2FADisabledSuccess"), null, 'mesgs');
            $user2fa = new User2FA($db); // Reset object
            $action = '';
        } else {
            setEventMessages($langs->trans("Error"), null, 'errors');
        }
    }
}

// Regenerate secret
if ($action == 'confirm_regenerate' && $confirm == 'yes') {
    if ($user2fa->id > 0) {
        $user2fa->delete($user);
        $user2fa = new User2FA($db);
        $user2fa->fk_user = $object->id;
        $result = $user2fa->create($user);
        if ($result > 0) {
            setEventMessages($langs->trans("SecretRegenerated"), null, 'mesgs');
            $action = 'setup';
        } else {
            setEventMessages($langs->trans("ErrorGeneratingSecret"), null, 'errors');
        }
    }
}

/*
 * View
 */

$title = $langs->trans("TwoFactorAuthentication");
llxHeader('', $title);

// User header
$head = user_prepare_head($object);

print dol_get_fiche_head($head, '2fa', $langs->trans("User"), -1, 'user');

// User card
$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// 2FA Status
print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans("2FAStatus").'</td><td>';
if ($user2fa->is_enabled) {
    print '<span class="badge badge-status4 badge-status">'.$langs->trans("Enabled").'</span>';
} else {
    print '<span class="badge badge-status1 badge-status">'.$langs->trans("Disabled").'</span>';
}
print '</td></tr>';
print '</table>';
print '</div>';

print '<br>';

// Show appropriate content based on status and action
if ($user2fa->is_enabled && $action != 'backup_codes') {
    // 2FA is enabled - show management options
    print '<div class="info">';
    print '<strong>'.$langs->trans("2FAEnabled").'</strong><br>';
    print $langs->trans("ProtectYourAccount");
    print '</div>';

    print '<br>';

    // Actions
    print '<div class="tabsAction">';

    // Regenerate secret
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=regenerate&id='.$object->id.'">';
    print $langs->trans("RegenerateSecret");
    print '</a>';

    // Disable 2FA
    print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?action=disable&id='.$object->id.'">';
    print $langs->trans("Disable2FA");
    print '</a>';

    print '</div>';

} elseif ($action == 'setup') {
    // Show QR code for setup
    $secret = $user2fa->getPlainSecret();
    global $mysoc;
    $issuer = !empty($mysoc->name) ? $mysoc->name : 'Dolibarr';
    $qrUrl = $user2fa->getQRCodeUrl($object->login, $issuer);

    print '<div class="center">';
    print '<h3>'.$langs->trans("SetupYour2FA").'</h3>';
    print '</div>';

    // QR Code
    print totp2fa_getQRCodeHTML($qrUrl, 250);

    // Manual entry
    print totp2fa_getManualEntryHTML($secret);

    // Verification form
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="verify">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';

    print '<div class="center" style="margin: 30px 0;">';
    print '<h4>'.$langs->trans("VerifyAndEnable").'</h4>';
    print '<p>'.$langs->trans("EnterCodeFromApp").'</p>';
    print '<input type="text" name="code" size="10" maxlength="6" placeholder="000000" ';
    print 'style="font-size: 24px; text-align: center; letter-spacing: 5px; padding: 10px;" ';
    print 'pattern="[0-9]{6}" required autofocus>';
    print '<br><br>';
    print '<input type="submit" class="button" value="'.$langs->trans("Verify").'">';
    print '</div>';

    print '</form>';

} elseif ($action == 'backup_codes') {
    // Show backup codes (one-time display)
    $backupCodes = isset($_SESSION['totp2fa_backup_codes']) ? $_SESSION['totp2fa_backup_codes'] : array();

    print '<div class="center">';
    print '<h3>'.$langs->trans("BackupCodesGenerated").'</h3>';
    print '</div>';

    print '<div class="warning">';
    print '<strong>'.$langs->trans("SaveBackupCodes").'</strong><br>';
    print $langs->trans("BackupCodesInfo");
    print '</div>';

    print '<br>';

    print '<div class="center">';
    print '<div style="display: inline-block; text-align: left; background: white; padding: 20px; border: 2px solid #333; border-radius: 5px;">';
    print '<h4 style="margin-top: 0;">'.$langs->trans("BackupCodes").'</h4>';
    print '<div style="font-family: monospace; font-size: 16px; line-height: 1.8;">';
    foreach ($backupCodes as $code) {
        print $code.'<br>';
    }
    print '</div>';
    print '</div>';
    print '</div>';

    print '<br>';

    print '<div class="center">';
    print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
    print $langs->trans("Continue");
    print '</a>';
    print '</div>';

    // Clear session
    unset($_SESSION['totp2fa_backup_codes']);

} else {
    // 2FA is disabled - show enable option
    print '<div class="info">';
    print '<strong>'.$langs->trans("EnhancedSecurity").'</strong><br>';
    print $langs->trans("2FAExplanation");
    print '</div>';

    print '<br>';

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("WhyUse2FA").'</td>';
    print '</tr>';
    print '<tr><td><ul>';
    print '<li>'.$langs->trans("2FABenefit1").'</li>';
    print '<li>'.$langs->trans("2FABenefit2").'</li>';
    print '<li>'.$langs->trans("2FABenefit3").'</li>';
    print '<li>'.$langs->trans("2FABenefit4").'</li>';
    print '</ul></td></tr>';
    print '</table>';
    print '</div>';

    print '<br>';

    // Enable button
    print '<div class="tabsAction">';
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-block;">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="enable_2fa">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="submit" class="butAction" value="'.$langs->trans("Enable2FA").'">';
    print '</form>';
    print '</div>';
}

print '</div>'; // fichecenter

print dol_get_fiche_end();

// Confirmation dialogs
if ($action == 'disable') {
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("Disable2FA"), $langs->trans("ConfirmDisable2FAForUser"), 'confirm_disable', '', 0, 1);
}

if ($action == 'regenerate') {
    print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("RegenerateSecret"), $langs->trans("ConfirmRegenerate"), 'confirm_regenerate', '', 0, 1);
}

llxFooter();
$db->close();
