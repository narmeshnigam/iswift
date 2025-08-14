<?php
// Database connection using environment-specific configuration

// Ensure configuration is loaded for DB credentials
if (!isset($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME)) {
    require_once __DIR__ . '/config.php';
}

$host = $DB_HOST ?? 'localhost';
$db   = $DB_NAME ?? '';
$user = $DB_USER ?? '';
$pass = $DB_PASS ?? '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>
