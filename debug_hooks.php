<?php
/**
 * Debug script to check if TOTP2FA hooks are registered
 */

define('NOCSRFCHECK', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '../../main.inc.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "TOTP2FA HOOK REGISTRATION DEBUG\n";
echo "========================================\n\n";

// Check if module is enabled
echo "1. MODULE STATUS:\n";
echo "   totp2fa enabled: " . (empty($conf->totp2fa->enabled) ? "NO" : "YES") . "\n";
echo "   Module constant: " . (defined('MAIN_MODULE_TOTP2FA') ? "DEFINED" : "NOT DEFINED") . "\n\n";

// Check modules_parts
echo "2. MODULES_PARTS['HOOKS']:\n";
if (isset($conf->modules_parts['hooks'])) {
    foreach ($conf->modules_parts['hooks'] as $module => $contexts) {
        $contextsStr = is_array($contexts) ? implode(', ', $contexts) : $contexts;
        echo "   [$module] => $contextsStr\n";
        if ($module === 'totp2fa' || $module === 'TOTP2FA') {
            echo "      ^^ TOTP2FA FOUND!\n";
        }
    }
} else {
    echo "   NOT SET!\n";
}
echo "\n";

// Check if hook file exists
echo "3. HOOK FILE CHECK:\n";
$hookFileLowercase = DOL_DOCUMENT_ROOT.'/custom/totp2fa/class/actions_totp2fa.class.php';
$hookFileUppercase = DOL_DOCUMENT_ROOT.'/custom/TOTP2FA/class/actions_TOTP2FA.class.php';
echo "   Lowercase path exists: " . (file_exists($hookFileLowercase) ? "YES" : "NO") . "\n";
echo "   Lowercase path: $hookFileLowercase\n";
echo "   Uppercase path exists: " . (file_exists($hookFileUppercase) ? "YES" : "NO") . "\n";
echo "   Uppercase path: $hookFileUppercase\n\n";

// Try to manually init hooks
echo "4. MANUAL HOOK INITIALIZATION TEST:\n";
$hookmanager->initHooks(array('mainloginpage'));
echo "   initHooks('mainloginpage') called\n";

if (isset($hookmanager->hooks['mainloginpage'])) {
    echo "   Hooks loaded for 'mainloginpage':\n";
    foreach ($hookmanager->hooks['mainloginpage'] as $module => $obj) {
        echo "      [$module] => " . get_class($obj) . "\n";
    }
} else {
    echo "   NO HOOKS LOADED for 'mainloginpage'!\n";
}
echo "\n";

// Check if class can be loaded manually
echo "5. MANUAL CLASS LOAD TEST:\n";
$testPath = '/totp2fa/class/actions_totp2fa.class.php';
echo "   Trying dol_include_once('$testPath')...\n";
$result = dol_include_once($testPath);
if ($result) {
    echo "   SUCCESS! File loaded.\n";
    if (class_exists('ActionsTotp2fa')) {
        echo "   Class 'ActionsTotp2fa' exists!\n";
        $testInstance = new ActionsTotp2fa($db);
        echo "   Instance created: " . get_class($testInstance) . "\n";
    } else {
        echo "   ERROR: Class 'ActionsTotp2fa' does NOT exist!\n";
    }
} else {
    echo "   FAILED! File could not be loaded.\n";
}
echo "\n";

// Check database constants
echo "6. DATABASE CONSTANTS:\n";
$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '%TOTP%' OR name LIKE '%totp%'";
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            echo "   ".$obj->name." = ".$obj->value."\n";
        }
    } else {
        echo "   No TOTP-related constants found!\n";
    }
} else {
    echo "   Database query failed!\n";
}
echo "\n";

echo "========================================\n";
echo "END DEBUG\n";
echo "========================================\n";
