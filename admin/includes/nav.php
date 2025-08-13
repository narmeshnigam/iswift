<?php include __DIR__ . '/config.php'; ?>
<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div>
        <div class="logo">
          <img src="<?= $BASE_URL ?>admin/assets/iSwift_logo.png" alt="iSwift Logo">
        </div>
      <nav>
        <a href="<?= $BASE_URL ?>admin/dashboard.php">Dashboard</a>
        <a href="<?= $BASE_URL ?>admin/products/list.php">Products</a>
        <a href="<?= $BASE_URL ?>admin/change_password.php">Change Password</a>
        <!-- Add more module links here -->
      </nav>
    </div>
    <div class="logout">
      <a href="<?= $BASE_URL ?>admin/logout.php">Logout</a>
    </div>
</div>
