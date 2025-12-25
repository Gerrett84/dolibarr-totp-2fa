<?php
/**
 * DIRECT database version - bypasses main.inc.php completely
 * This is a diagnostic version to isolate the problem
 */

// Ensure no output before headers
ob_start();

// Set JSON header immediately
header('Content-Type: application/json');
header('X-Debug-Direct: true');

// Load Dolibarr config directly
$configfile = '../../../conf/conf.php';
if (!file_exists($configfile)) {
    echo json_encode(array('error' => 'Config file not found', 'has_2fa' => false));
    exit;
}

// Parse config file for database credentials
$config_content = file_get_contents($configfile);
if (preg_match('/\$dolibarr_main_db_host\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $db_host = $matches[1];
}
if (preg_match('/\$dolibarr_main_db_name\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $db_name = $matches[1];
}
if (preg_match('/\$dolibarr_main_db_user\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $db_user = $matches[1];
}
if (preg_match('/\$dolibarr_main_db_pass\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $db_pass = $matches[1];
}
if (preg_match('/\$dolibarr_main_db_prefix\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $db_prefix = $matches[1];
}

// Get username from POST/GET
$username = isset($_POST['username']) ? $_POST['username'] : (isset($_GET['username']) ? $_GET['username'] : '');

if (empty($username)) {
    echo json_encode(array('error' => 'No username provided', 'has_2fa' => false));
    exit;
}

// Connect to database
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get user ID
    $stmt = $conn->prepare("SELECT rowid FROM {$db_prefix}user WHERE login = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(array('has_2fa' => false, 'debug' => 'user_not_found'));
        exit;
    }

    $user_id = $user['rowid'];

    // Check if user has 2FA enabled
    $stmt = $conn->prepare("SELECT is_enabled FROM {$db_prefix}totp2fa_user_settings WHERE fk_user = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $has_2fa = ($settings && $settings['is_enabled'] == 1);

    echo json_encode(array(
        'has_2fa' => $has_2fa,
        'debug' => array(
            'user_id' => $user_id,
            'username' => $username,
            'settings_found' => ($settings ? 'yes' : 'no')
        )
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'error' => 'Database error: ' . $e->getMessage(),
        'has_2fa' => false
    ));
}

// Clear any buffered output before our JSON
ob_end_clean();
