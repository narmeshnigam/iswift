<?php
session_start();
include_once 'includes/config.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: {$BASE_URL}login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password – iSwift Website Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $BASE_URL ?>admin/assets/style.css">
</head>
<body class="sidebar-layout">

<?php include __DIR__ . '/includes/nav.php'; ?>

<!-- Main Content -->
<div class="main-content">
  <h1>Change Your Password</h1>
  <form class="change-password-form" method="post" action="process_change_password.php">
    <label for="current_password">Current Password</label>
    <input type="password" id="current_password" name="current_password" required>

    <label for="new_password">New Password</label>
    <input type="password" id="new_password" name="new_password" required>

    <label for="confirm_password">Confirm New Password</label>
    <input type="password" id="confirm_password" name="confirm_password" required>

  <button type="submit">Update Password</button>
  </form>
</div>

<script src="<?= $BASE_URL ?>admin/assets/nav.js"></script>
</body>
</html>