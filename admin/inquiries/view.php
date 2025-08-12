<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

$id=(int)get('id');
$stmt=db()->prepare('SELECT i.*,p.title as product_title FROM inquiries i LEFT JOIN products p ON p.id=i.product_id WHERE i.id=?');
$stmt->execute([$id]);
$inq=$stmt->fetch();
if(!$inq){ redirect('list.php'); }

include __DIR__ . '/../_inc/header.php';
?>
<h2>Inquiry View</h2>
<?php if($msg=flash_get("success")) echo "<p class=\"card\">$msg</p>"; ?>
<div class="card">
<p><strong>Name:</strong> <?php echo e($inq['name']); ?></p>
<p><strong>Email:</strong> <?php echo e($inq['email']); ?></p>
<p><strong>Phone:</strong> <?php echo e($inq['phone']); ?></p>
<p><strong>Source:</strong> <?php echo e($inq['source']); ?></p>
<?php if($inq['product_title']): ?><p><strong>Product:</strong> <?php echo e($inq['product_title']); ?></p><?php endif; ?>
<p><strong>Message:</strong><br><?php echo nl2br(e($inq['message'])); ?></p>
<p><strong>Status:</strong> <?php echo e($inq['status']); ?></p>
<form method="post" action="update_status.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $inq['id']; ?>">
    <select name="status">
        <option value="new" <?php if($inq['status']==='new') echo 'selected';?>>New</option>
        <option value="in_progress" <?php if($inq['status']==='in_progress') echo 'selected';?>>In Progress</option>
        <option value="closed" <?php if($inq['status']==='closed') echo 'selected';?>>Closed</option>
    </select>
    <button type="submit">Update Status</button>
</form>
<a href="list.php">&larr; Back to list</a>
</div>
<?php include __DIR__ . '/../_inc/footer.php';
