<?php
// Environment-aware configuration for base URL and database credentials

$serverHost = $_SERVER['HTTP_HOST'] ?? '';

if ($serverHost === 'localhost' || $serverHost === '127.0.0.1') {
    // Local development settings
    $BASE_URL = '/iswift/';

    $DB_HOST = 'localhost';
    $DB_NAME = 'iswift_db';
    $DB_USER = 'root';
    $DB_PASS = '';
} else {
    // Production settings
    $BASE_URL = '/';

    $DB_HOST = 'localhost';
    $DB_NAME = 'u348991914_iswift';
    $DB_USER = 'u348991914_iswift';
    $DB_PASS = 'Z@q@@Fu|fQ$3';
}

// Define constants for broader compatibility
if (!defined('DB_HOST')) define('DB_HOST', $DB_HOST);
if (!defined('DB_NAME')) define('DB_NAME', $DB_NAME);
if (!defined('DB_USER')) define('DB_USER', $DB_USER);
if (!defined('DB_PASS')) define('DB_PASS', $DB_PASS);
?>
