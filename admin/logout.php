<?php
session_start();
session_unset();
session_destroy();
include __DIR__ . '/includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logged Out – iSwift ERP</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <meta http-equiv="refresh" content="2;url=<?= $BASE_URL ?>admin/index.php">
  <link rel="stylesheet" href="<?= $BASE_URL ?>admin/assets/style.css">
</head>
<body class="logout-page">
  <div class="logout-message">
    <h2>You’ve been logged out</h2>
    <p>Redirecting to login page...</p>
  </div>
</body>
</html>
