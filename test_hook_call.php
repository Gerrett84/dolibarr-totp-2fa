<?php
/**
 * Direct test of hook execution
 */

define('NOCSRFCHECK', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '../../main.inc.php';

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "DIRECT HOOK EXECUTION TEST\n";
echo "========================================\n\n";

// Initialize hooks
echo "1. Initializing hooks for 'mainloginpage'...\n";
$hookmanager->initHooks(array('mainloginpage'));
echo "   Done.\n\n";

// Check what hooks are loaded
echo "2. Loaded hooks:\n";
if (isset($hookmanager->hooks['mainloginpage'])) {
    foreach ($hookmanager->hooks['mainloginpage'] as $module => $obj) {
        echo "   [$module] => " . get_class($obj) . "\n";
    }
} else {
    echo "   NO HOOKS LOADED!\n";
}
echo "\n";

// Execute getLoginPageExtraContent hook
echo "3. Executing 'getLoginPageExtraContent' hook...\n";
$parameters = array();
$dummyobject = new stdClass();
$action = '';
$result = $hookmanager->executeHooks('getLoginPageExtraContent', $parameters, $dummyobject, $action);
echo "   Return value: $result\n";
echo "   resPrint content:\n";
echo "---BEGIN---\n";
echo $hookmanager->resPrint;
echo "\n---END---\n\n";

// Try calling the method directly
echo "4. Calling method directly on hook object...\n";
if (isset($hookmanager->hooks['mainloginpage']['totp2fa'])) {
    $hookobj = $hookmanager->hooks['mainloginpage']['totp2fa'];
    echo "   Found hook object: " . get_class($hookobj) . "\n";

    if (method_exists($hookobj, 'getLoginPageExtraContent')) {
        echo "   Method 'getLoginPageExtraContent' EXISTS\n";
        $result = $hookobj->getLoginPageExtraContent($parameters, $dummyobject, $action, $hookmanager);
        echo "   Direct call return value: $result\n";
        echo "   resPrint from object:\n";
        echo "---BEGIN---\n";
        echo $hookobj->resPrint;
        echo "\n---END---\n";
    } else {
        echo "   Method 'getLoginPageExtraContent' DOES NOT EXIST!\n";
        echo "   Available methods:\n";
        $methods = get_class_methods($hookobj);
        foreach ($methods as $method) {
            echo "      - $method\n";
        }
    }
} else {
    echo "   Hook object not found!\n";
}

echo "\n========================================\n";
echo "END TEST\n";
echo "========================================\n";
