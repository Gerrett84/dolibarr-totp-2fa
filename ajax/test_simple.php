<?php
/**
 * Simple test endpoint - NO Dolibarr includes
 * This tests if basic PHP execution and JSON output works
 */

// Set headers FIRST before any output
header('Content-Type: application/json');
header('X-Debug-Test: SimpleTest');

// Return simple JSON
echo json_encode(array(
    'test' => 'success',
    'message' => 'Basic PHP and JSON working',
    'timestamp' => time()
));
exit;
