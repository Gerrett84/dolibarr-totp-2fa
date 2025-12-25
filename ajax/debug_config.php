<?php
/**
 * Debug script to check conf.php values
 */

header('Content-Type: text/plain');

$configfile = '../../../conf/conf.php';
echo "Config file path: $configfile\n";
echo "File exists: " . (file_exists($configfile) ? 'YES' : 'NO') . "\n\n";

if (file_exists($configfile)) {
    require_once $configfile;

    echo "Database Configuration:\n";
    echo "----------------------\n";
    echo "Host: " . (isset($dolibarr_main_db_host) ? $dolibarr_main_db_host : 'NOT SET') . "\n";
    echo "Name: " . (isset($dolibarr_main_db_name) ? $dolibarr_main_db_name : 'NOT SET') . "\n";
    echo "User: " . (isset($dolibarr_main_db_user) ? $dolibarr_main_db_user : 'NOT SET') . "\n";
    echo "Pass: " . (isset($dolibarr_main_db_pass) ? '***' . substr($dolibarr_main_db_pass, -3) : 'NOT SET') . "\n";
    echo "Prefix: " . (isset($dolibarr_main_db_prefix) ? $dolibarr_main_db_prefix : 'NOT SET') . "\n";
    echo "Type: " . (isset($dolibarr_main_db_type) ? $dolibarr_main_db_type : 'NOT SET') . "\n";
}
