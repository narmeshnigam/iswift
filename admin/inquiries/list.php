<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

$page = max(1,(int)get('page',1));
$per=10; $offset=($page-1)*$per;
$search = get('q');
$status = get('status','');
$source = get('source','');

$conds=[];$params=[];
if($search){ $conds[]='(i.name LIKE ? OR i.email LIKE ? OR i.phone LIKE ?)'; $params=array_merge($params,array_fill(0,3,'%'.$search.'%')); }
if($status){ $conds[]='i.status=?'; $params[]=$status; }
if($source){ $conds[]='i.source=?'; $params[]=$source; }
$where = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$totalStmt=db()->prepare("SELECT COUNT(*) FROM inquiries i $where");$totalStmt->execute($params);$total=(int)$totalStmt->fetchColumn();$total_pages=max(1,(int)ceil($total/$per));

$listStmt=db()->prepare("SELECT i.*,p.title as product_title FROM inquiries i LEFT JOIN products p ON p.id=i.product_id $where ORDER BY i.created_at DESC LIMIT $per OFFSET $offset");
$listStmt->execute($params);
$inquiries=$listStmt->fetchAll();

include __DIR__ . '/../_inc/header.php';
?>
<h2>Inquiries</h2>
<form method="get" class="card">
    <input type="text" name="q" placeholder="Search" value="<?php echo e($search); ?>">
    <select name="status">
        <option value="" <?php if($status==='') echo 'selected';?>>All Status</option>
        <option value="new" <?php if($status==='new') echo 'selected';?>>New</option>
        <option value="in_progress" <?php if($status==='in_progress') echo 'selected';?>>In Progress</option>
        <option value="closed" <?php if($status==='closed') echo 'selected';?>>Closed</option>
    </select>
    <select name="source">
        <option value="" <?php if($source==='') echo 'selected';?>>All Sources</option>
        <option value="contact" <?php if($source==='contact') echo 'selected';?>>Contact</option>
        <option value="product" <?php if($source==='product') echo 'selected';?>>Product</option>
        <option value="other" <?php if($source==='other') echo 'selected';?>>Other</option>
    </select>
    <button type="submit">Filter</button>
</form>
<table class="table">
<tr><th>Name</th><th>Source</th><th>Product</th><th>Created</th><th>Status</th><th>Actions</th></tr>
<?php foreach($inquiries as $i): ?>
<tr>
    <td><?php echo e($i['name']); ?></td>
    <td><?php echo e($i['source']); ?></td>
    <td><?php echo e($i['product_title']); ?></td>
    <td><?php echo e($i['created_at']); ?></td>
    <td><?php echo e($i['status']); ?></td>
    <td><a href="view.php?id=<?php echo $i['id']; ?>">View</a></td>
</tr>
<?php endforeach; ?>
</table>
<div>
<?php for($p=1;$p<=$total_pages;$p++): ?>
<a href="?page=<?php echo $p; ?>&q=<?php echo e($search); ?>&status=<?php echo e($status); ?>&source=<?php echo e($source); ?>" <?php if($page==$p) echo 'style="font-weight:bold"';?>><?php echo $p; ?></a>
<?php endfor; ?>
</div>
<?php include __DIR__ . '/../_inc/footer.php';
