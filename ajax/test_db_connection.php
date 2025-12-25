<?php
/**
 * Test database connection with exact Dolibarr credentials
 */

header('Content-Type: text/plain');

$configfile = '../../../conf/conf.php';
require_once $configfile;

echo "Testing database connection...\n\n";
echo "Host: $dolibarr_main_db_host\n";
echo "Name: $dolibarr_main_db_name\n";
echo "User: $dolibarr_main_db_user\n";
echo "Type: $dolibarr_main_db_type\n\n";

// Test with mysqli (like Dolibarr uses)
echo "Attempting mysqli connection...\n";

try {
    // Suppress errors and capture them
    mysqli_report(MYSQLI_REPORT_OFF);

    $mysqli = @new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name);

    if ($mysqli->connect_error) {
        echo "FAILED: " . $mysqli->connect_error . "\n";
        echo "Error number: " . $mysqli->connect_errno . "\n";

        // Try to determine if password is encrypted
        if ($mysqli->connect_errno == 1045) {
            echo "\nPassword might be encrypted. Let me check if Dolibarr uses encryption...\n";
            if (function_exists('dol_decode')) {
                echo "dol_decode() function exists - password IS encrypted!\n";
            } else {
                echo "dol_decode() function NOT available in this context.\n";
            }
        }
    } else {
        echo "SUCCESS!\n\n";

        // Test query
        echo "Testing query on {$dolibarr_main_db_prefix}user table...\n";
        $sql = "SELECT COUNT(*) as count FROM {$dolibarr_main_db_prefix}user";
        $result = $mysqli->query($sql);

        if ($result) {
            $row = $result->fetch_assoc();
            echo "SUCCESS! Found {$row['count']} users in database.\n";
        } else {
            echo "FAILED: " . $mysqli->error . "\n";
        }

        $mysqli->close();
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nTest complete.\n";
