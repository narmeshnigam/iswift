<?php
$host = 'localhost';
$db   = 'u348991914_iswift';
$user = 'u348991914_iswift';
$pass = 'Z@q@@Fu|fQ$3'; // Replace with actual

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
