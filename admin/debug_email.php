<?php
/* Debug script for email testing - DELETE AFTER USE */

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

// Security check
if (!$user->admin) {
    accessforbidden();
}

echo "<h1>TOTP 2FA Email Debug</h1>";
echo "<pre>";

// Check 1: Activity Log Table
echo "=== 1. Activity Log Table Check ===\n";
$sql = "SHOW TABLES LIKE 'llx_totp2fa_activity_log'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    echo "✓ Table llx_totp2fa_activity_log EXISTS\n";

    // Count entries
    $sql2 = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_activity_log";
    $resql2 = $db->query($sql2);
    $obj = $db->fetch_object($resql2);
    echo "  → Entries in table: " . $obj->cnt . "\n";

    // Show recent entries
    $sql3 = "SELECT * FROM ".MAIN_DB_PREFIX."totp2fa_activity_log ORDER BY datec DESC LIMIT 10";
    $resql3 = $db->query($sql3);
    echo "\n  Recent log entries:\n";
    while ($obj = $db->fetch_object($resql3)) {
        echo "    - " . $obj->datec . " | User " . $obj->fk_user . " | " . $obj->action . " | " . $obj->ip_address . "\n";
    }
} else {
    echo "✗ Table llx_totp2fa_activity_log DOES NOT EXIST!\n";
    echo "  → Please disable and re-enable the module to create the table.\n";
}

// Check 2: Email Configuration
echo "\n=== 2. Email Configuration ===\n";
echo "MAIN_MAIL_SENDMODE: " . (isset($conf->global->MAIN_MAIL_SENDMODE) ? $conf->global->MAIN_MAIL_SENDMODE : 'NOT SET') . "\n";
echo "MAIN_MAIL_EMAIL_FROM: " . (isset($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'NOT SET') . "\n";
echo "MAIN_MAIL_SMTPS_HOST: " . (isset($conf->global->MAIN_MAIL_SMTPS_HOST) ? $conf->global->MAIN_MAIL_SMTPS_HOST : 'NOT SET') . "\n";

// Check 3: Current User Email
echo "\n=== 3. Current User ===\n";
echo "User ID: " . $user->id . "\n";
echo "Login: " . $user->login . "\n";
echo "Email: " . ($user->email ? $user->email : 'NOT SET') . "\n";

// Check 4: Test Email Send
echo "\n=== 4. Test Email Send ===\n";
if (!empty($user->email)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

    $from = !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@'.$_SERVER['SERVER_NAME'];
    $to = $user->email;
    $subject = "TOTP 2FA Test Email";
    $body = "This is a test email from the TOTP 2FA module.\n\nIf you receive this, email sending works!";

    echo "From: $from\n";
    echo "To: $to\n";
    echo "Subject: $subject\n\n";

    $mail = new CMailFile(
        $subject,
        $to,
        $from,
        $body,
        array(),
        array(),
        array(),
        '',
        '',
        0,
        0
    );

    $result = $mail->sendfile();

    if ($result) {
        echo "✓ Email sent successfully!\n";
    } else {
        echo "✗ Email sending FAILED!\n";
        echo "Error: " . $mail->error . "\n";
    }
} else {
    echo "✗ Cannot test - current user has no email address.\n";
}

// Check 5: Count failed attempts function
echo "\n=== 5. Failed Attempts Count Test ===\n";
dol_include_once('/totp2fa/class/totp2fa_activity.class.php');

if (class_exists('Totp2faActivity')) {
    $activity = new Totp2faActivity($db);
    $count = $activity->countRecentFailedAttempts($user->id, 5);
    echo "Failed attempts for current user in last 5 minutes: $count\n";
} else {
    echo "✗ Totp2faActivity class not found!\n";
}

echo "\n</pre>";
echo "<p><strong>After checking, delete this file:</strong> /admin/debug_email.php</p>";
