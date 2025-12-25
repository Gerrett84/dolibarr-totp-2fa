<?php
/**
 * Test using Dolibarr's database wrapper
 */

// Load Dolibarr
define('NOCSRFCHECK', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOREQUIREUSER', 1);
define('NOTOKENRENEWAL', 1);

header('Content-Type: text/plain');

require '../../../main.inc.php';

echo "Testing with Dolibarr's database wrapper...\n\n";

echo "Database object exists: " . (isset($db) ? "YES" : "NO") . "\n";

if (isset($db)) {
    echo "Database connected: " . ($db->connected ? "YES" : "NO") . "\n";
    echo "Database type: " . $db->type . "\n\n";

    // Test query
    echo "Testing query on llx_user table...\n";
    $sql = "SELECT COUNT(*) as count FROM ".MAIN_DB_PREFIX."user";
    $resql = $db->query($sql);

    if ($resql) {
        $obj = $db->fetch_object($resql);
        echo "SUCCESS! Found " . $obj->count . " users in database.\n\n";

        // Now test for totp2fa tables
        echo "Testing totp2fa tables...\n";
        $sql = "SELECT COUNT(*) as count FROM ".MAIN_DB_PREFIX."totp2fa_user_settings";
        $resql = $db->query($sql);

        if ($resql) {
            $obj = $db->fetch_object($resql);
            echo "SUCCESS! Found " . $obj->count . " 2FA settings in database.\n";
        } else {
            echo "FAILED: " . $db->error() . "\n";
        }
    } else {
        echo "FAILED: " . $db->error() . "\n";
    }
}

echo "\nTest complete.\n";
