<?php
require_once __DIR__ . '/auth_guard.php';
$admin = require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>iSwift Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<header>
    <div><strong>iSwift Admin</strong></div>
    <nav>
        <a href="/admin/index.php">Dashboard</a>
        <a href="/admin/products/list.php">Products</a>
        <a href="/admin/inquiries/list.php">Inquiries</a>
        <a href="/admin/account/change_password.php">Account</a>
        <a href="/admin/logout.php">Logout (<?php echo e($admin['name']); ?>)</a>
    </nav>
</header>
<main style="padding:20px;">
