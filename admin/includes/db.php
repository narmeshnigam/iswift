<?php
$host = 'localhost';
$db   = 'iswift_db';
$user = 'root';
$pass = ''; // Replace with actual

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
