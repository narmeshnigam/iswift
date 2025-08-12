<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/_inc/auth_guard.php';

// Stats queries
$total_products = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$active_products = (int)db()->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn();
$total_inquiries = (int)db()->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
$new_inquiries = (int)db()->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn();

$latest_inquiries = db()->query('SELECT name,source,created_at,status FROM inquiries ORDER BY created_at DESC LIMIT 5')->fetchAll();
$latest_products = db()->query('SELECT title,is_active,created_at FROM products ORDER BY created_at DESC LIMIT 5')->fetchAll();

include __DIR__ . '/_inc/header.php';
?>
<h2>Dashboard</h2>
<div class="grid">
    <div class="card">Total Products<br><strong><?php echo $total_products; ?></strong></div>
    <div class="card">Active Products<br><strong><?php echo $active_products; ?></strong></div>
    <div class="card">Total Inquiries<br><strong><?php echo $total_inquiries; ?></strong></div>
    <div class="card">New Inquiries<br><strong><?php echo $new_inquiries; ?></strong></div>
</div>
<div class="card">
    <h3>Latest Inquiries</h3>
    <table class="table">
        <tr><th>Name</th><th>Source</th><th>Date</th><th>Status</th></tr>
        <?php foreach ($latest_inquiries as $inq): ?>
        <tr>
            <td><?php echo e($inq['name']); ?></td>
            <td><?php echo e($inq['source']); ?></td>
            <td><?php echo e($inq['created_at']); ?></td>
            <td><?php echo e($inq['status']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <a href="/admin/inquiries/list.php">View all inquiries</a>
</div>
<div class="card">
    <h3>Latest Products</h3>
    <table class="table">
        <tr><th>Title</th><th>Active</th><th>Created</th></tr>
        <?php foreach ($latest_products as $p): ?>
        <tr>
            <td><?php echo e($p['title']); ?></td>
            <td><?php echo $p['is_active'] ? 'Yes' : 'No'; ?></td>
            <td><?php echo e($p['created_at']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <a href="/admin/products/list.php">View all products</a>
</div>
<?php include __DIR__ . '/_inc/footer.php';
