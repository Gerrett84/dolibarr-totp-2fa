<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       login_2fa.php
 * \ingroup    totp2fa
 * \brief      2FA code verification page (after successful password login)
 */

// This page is called after successful username/password authentication
// User must enter 2FA code to complete login

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

dol_include_once('/totp2fa/class/user2fa.class.php');

// Load translations
$langs->loadLangs(array("main", "totp2fa@totp2fa"));

// Check if we have a valid partial login session
if (empty($_SESSION['totp2fa_pending_login']) || empty($_SESSION['totp2fa_user_id'])) {
    // No pending 2FA, redirect to login
    header('Location: '.DOL_URL_ROOT.'/');
    exit;
}

$user_id = $_SESSION['totp2fa_user_id'];
$action = GETPOST('action', 'aZ09');
$code = GETPOST('code', 'alpha');
$use_backup = GETPOST('use_backup', 'int');

// Trusted device settings - read directly from DB as $conf may not be fully loaded on login page
$trustedEnabled = 0;
$trustedDays = 30;
$sqlConf = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name IN ('TOTP2FA_TRUSTED_DEVICE_ENABLED', 'TOTP2FA_TRUSTED_DEVICE_DAYS')";
$resqlConf = $db->query($sqlConf);
if ($resqlConf) {
    while ($objConf = $db->fetch_object($resqlConf)) {
        if ($objConf->name == 'TOTP2FA_TRUSTED_DEVICE_ENABLED') {
            $trustedEnabled = (int)$objConf->value;
        }
        if ($objConf->name == 'TOTP2FA_TRUSTED_DEVICE_DAYS') {
            $trustedDays = (int)$objConf->value;
        }
    }
}

// Generate device hash for trusted device feature
function getDeviceHash() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    // Create a hash based on browser fingerprint
    return hash('sha256', $userAgent . '|' . $acceptLang);
}

// Check if current device is trusted
function isDeviceTrusted($db, $user_id) {
    $deviceHash = getDeviceHash();
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " WHERE fk_user = ".(int)$user_id;
    $sql .= " AND device_hash = '".$db->escape($deviceHash)."'";
    $sql .= " AND trusted_until > NOW()";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        // Update last use
        $obj = $db->fetch_object($resql);
        $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
        $sqlUpdate .= " SET date_last_use = NOW()";
        $sqlUpdate .= " WHERE rowid = ".(int)$obj->rowid;
        $db->query($sqlUpdate);
        return true;
    }
    return false;
}

// Save device as trusted
function trustDevice($db, $user_id, $days) {
    $deviceHash = getDeviceHash();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Detect device name from user agent
    $deviceName = 'Unbekanntes Gerät';
    if (preg_match('/iPhone|iPad/', $userAgent)) {
        $deviceName = 'Apple iOS';
    } elseif (preg_match('/Android/', $userAgent)) {
        $deviceName = 'Android';
    } elseif (preg_match('/Windows/', $userAgent)) {
        $deviceName = 'Windows PC';
    } elseif (preg_match('/Macintosh/', $userAgent)) {
        $deviceName = 'Mac';
    } elseif (preg_match('/Linux/', $userAgent)) {
        $deviceName = 'Linux';
    }

    // Delete existing entry for this device
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " WHERE fk_user = ".(int)$user_id;
    $sql .= " AND device_hash = '".$db->escape($deviceHash)."'";
    $db->query($sql);

    // Insert new entry
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " (fk_user, device_hash, device_name, ip_address, user_agent, trusted_until, date_creation)";
    $sql .= " VALUES (";
    $sql .= (int)$user_id.",";
    $sql .= "'".$db->escape($deviceHash)."',";
    $sql .= "'".$db->escape($deviceName)."',";
    $sql .= "'".$db->escape($ip)."',";
    $sql .= "'".$db->escape(substr($userAgent, 0, 500))."',";
    $sql .= "DATE_ADD(NOW(), INTERVAL ".(int)$days." DAY),";
    $sql .= "NOW()";
    $sql .= ")";

    return $db->query($sql);
}

// Check if this device is already trusted
if ($trustedEnabled && isDeviceTrusted($db, $user_id)) {
    // Device is trusted, skip 2FA
    $_SESSION['totp2fa_verified'] = $user_id;
    unset($_SESSION['totp2fa_pending_login']);
    unset($_SESSION['totp2fa_user_id']);

    $urltogo = isset($_SESSION['totp2fa_urltogo']) ? $_SESSION['totp2fa_urltogo'] : DOL_URL_ROOT.'/';
    unset($_SESSION['totp2fa_urltogo']);

    header('Location: '.$urltogo);
    exit;
}

// Load user 2FA settings
$user2fa = new User2FA($db);
$result = $user2fa->fetch($user_id);

if ($result <= 0 || !$user2fa->is_enabled) {
    // 2FA not enabled, this shouldn't happen
    unset($_SESSION['totp2fa_pending_login']);
    unset($_SESSION['totp2fa_user_id']);
    header('Location: '.DOL_URL_ROOT.'/');
    exit;
}

/*
 * Actions
 */

$error = 0;
$errors = array();

if ($action == 'verify' && !empty($code)) {
    if ($use_backup) {
        // Verify backup code
        $isValid = $user2fa->verifyBackupCode($code);
        if ($isValid) {
            // Automatically save trusted device if feature is enabled
            if ($trustedEnabled) {
                trustDevice($db, $user_id, $trustedDays);
            }

            // Login successful! Mark as verified in this session
            $_SESSION['totp2fa_verified'] = $user_id;
            unset($_SESSION['totp2fa_pending_login']);
            unset($_SESSION['totp2fa_user_id']);

            // Redirect to intended page or home
            $urltogo = isset($_SESSION['totp2fa_urltogo']) ? $_SESSION['totp2fa_urltogo'] : DOL_URL_ROOT.'/';
            unset($_SESSION['totp2fa_urltogo']);

            header('Location: '.$urltogo);
            exit;
        } else {
            $error++;
            $errors[] = $langs->trans("InvalidCode");
        }
    } else {
        // Verify TOTP code
        $isValid = $user2fa->verifyCode($code);
        if ($isValid) {
            // Automatically save trusted device if feature is enabled
            if ($trustedEnabled) {
                trustDevice($db, $user_id, $trustedDays);
            }

            // Login successful! Mark as verified in this session
            $_SESSION['totp2fa_verified'] = $user_id;
            unset($_SESSION['totp2fa_pending_login']);
            unset($_SESSION['totp2fa_user_id']);

            // Redirect to intended page or home
            $urltogo = isset($_SESSION['totp2fa_urltogo']) ? $_SESSION['totp2fa_urltogo'] : DOL_URL_ROOT.'/';
            unset($_SESSION['totp2fa_urltogo']);

            header('Location: '.$urltogo);
            exit;
        } else {
            $error++;
            $errors[] = $user2fa->error ? $user2fa->error : $langs->trans("InvalidCode");
        }
    }
}

/*
 * View
 */

$title = $langs->trans("TwoFactorAuthentication");

// Minimal header (no menu)
top_htmlhead('', $title);

print '<body class="body bodylogin">';
print '<div class="login_center center">';
print '<div class="login_vertical_align">';

print '<form id="login" name="login" method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="verify">';
print '<input type="hidden" name="use_backup" id="use_backup" value="0">';

// Logo
if (!empty($mysoc->logo)) {
    $urllogo = DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/'.$mysoc->logo);
} else {
    $urllogo = DOL_URL_ROOT.'/theme/dolibarr_logo.svg';
}
print '<div class="login_table_title center" title="'.$title.'">';
print '<img alt="Logo" src="'.$urllogo.'" id="img_logo">';
print '</div>';

print '<div class="login_table">';

// Title
print '<div class="tagtable center login_main_message">';
print '<div class="trinline">';
print '<div class="tdinline login_main_message_title">';
print '<div class="title">'.$langs->trans("2FARequired").'</div>';
print '</div>';
print '</div>';
print '</div>';

// Instructions
print '<div class="tagtable center login_main_body">';
print '<div class="trinline">';
print '<div class="tdinline">';
print '<div style="margin: 20px 0; color: #666;">';
print $langs->trans("PleaseEnter2FACode");
print '</div>';
print '</div>';
print '</div>';
print '</div>';

// Error messages
if ($error) {
    print '<div class="tagtable center login_main_message">';
    print '<div class="trinline">';
    print '<div class="tdinline">';
    print '<div class="error">';
    foreach ($errors as $err) {
        print $err.'<br>';
    }
    print '</div>';
    print '</div>';
    print '</div>';
    print '</div>';
}

// Code input
print '<div class="tagtable center login_table_secours">';
print '<div class="trinline">';
print '<div class="tdinline nowraponall center valignmiddle">';
print '<input type="text" name="code" id="code" class="flat input-lg" ';
print 'placeholder="000000" maxlength="10" ';
print 'style="font-size: 28px; text-align: center; letter-spacing: 8px; width: 250px; padding: 15px;" ';
print 'autofocus required>';
print '</div>';
print '</div>';
print '</div>';

// Info about trusted device (if enabled)
if ($trustedEnabled) {
    print '<div class="tagtable center" style="margin-bottom: 15px;">';
    print '<div class="trinline">';
    print '<div class="tdinline">';
    print '<div style="font-size: 12px; color: #666; background: #f5f5f5; padding: 8px 12px; border-radius: 4px;">';
    print '🔒 Dieses Gerät wird '.$trustedDays.' Tage gespeichert';
    print '</div>';
    print '</div>';
    print '</div>';
    print '</div>';
}

// Submit button
print '<div class="tagtable center login_table_secours">';
print '<div class="trinline">';
print '<div class="tdinline nowraponall center valignmiddle">';
print '<input type="submit" class="button" value="'.$langs->trans("Verify").'" style="padding: 10px 30px; font-size: 16px;">';
print '</div>';
print '</div>';
print '</div>';

// Backup code link
print '<div class="tagtable center" style="margin-top: 20px;">';
print '<div class="trinline">';
print '<div class="tdinline">';
print '<a href="#" onclick="toggleBackupCode(); return false;" style="font-size: 13px;">';
print $langs->trans("UseBackupCode");
print '</a>';
print '</div>';
print '</div>';
print '</div>';

// Time remaining (countdown)
$totp = new TOTP();
$remaining = $totp->getRemainingSeconds();
print '<div class="tagtable center" style="margin-top: 15px;">';
print '<div class="trinline">';
print '<div class="tdinline">';
print '<div id="countdown" style="font-size: 12px; color: #999;">';
print $langs->trans("CodeExpiresIn").' <span id="timer">'.$remaining.'</span> '.$langs->trans("Seconds");
print '</div>';
print '</div>';
print '</div>';
print '</div>';

print '</div>'; // login_table

print '</form>';

print '</div>'; // login_vertical_align
print '</div>'; // login_center

// JavaScript for backup code toggle and countdown
print '<script>';
print 'function toggleBackupCode() {';
print '  var useBackup = document.getElementById("use_backup");';
print '  var codeInput = document.getElementById("code");';
print '  if (useBackup.value == "0") {';
print '    useBackup.value = "1";';
print '    codeInput.placeholder = "1234-5678";';
print '    codeInput.maxLength = "9";';
print '  } else {';
print '    useBackup.value = "0";';
print '    codeInput.placeholder = "000000";';
print '    codeInput.maxLength = "6";';
print '  }';
print '  codeInput.value = "";';
print '  codeInput.focus();';
print '}';

// Countdown timer
print 'var timeRemaining = '.$remaining.';';
print 'setInterval(function() {';
print '  timeRemaining--;';
print '  if (timeRemaining <= 0) {';
print '    timeRemaining = 30;'; // Reset to 30 seconds
print '  }';
print '  document.getElementById("timer").innerText = timeRemaining;';
print '}, 1000);';
print '</script>';

print '</body>';
print '</html>';

$db->close();
