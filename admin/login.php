<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if ($password === $user['password']) { // plain-text check
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        header("Location: {$BASE_URL}admin/dashboard.php");
        exit();
    }
}

$_SESSION['error'] = "Invalid email or password.";
header("Location: {$BASE_URL}index.php");
exit();
?>
