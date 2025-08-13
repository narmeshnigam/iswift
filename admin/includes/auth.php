<?php
session_start();
include __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: {$BASE_URL}index.php");
    exit();
}
?>
