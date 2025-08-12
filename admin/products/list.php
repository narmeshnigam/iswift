<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

$page = max(1, (int)get('page',1));
$per = 10;
$offset = ($page-1)*$per;

$search = get('q');
$status = get('status','all');
$category_id = (int)get('category_id');
$sort = get('sort','created_desc');
$sorts = ['created_desc'=>'p.created_at DESC','created_asc'=>'p.created_at ASC'];
$order = $sorts[$sort] ?? $sorts['created_desc'];

$conds = [];$params=[];
if ($search) {
    $conds[] = '(p.title LIKE ? OR p.sku LIKE ? OR p.short_desc LIKE ?)';
    $params = array_merge($params, array_fill(0,3,'%'.$search.'%'));
}
if ($category_id) { $conds[]='p.category_id=?'; $params[]=$category_id; }
if ($status==='active') { $conds[]='p.is_active=1'; }
$where = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$totalStmt = db()->prepare("SELECT COUNT(*) FROM products p $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total/$per));

$listStmt = db()->prepare("SELECT p.*,c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id $where ORDER BY $order LIMIT $per OFFSET $offset");
$listStmt->execute($params);
$products = $listStmt->fetchAll();

$cats = db()->query('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name')->fetchAll();

include __DIR__ . '/../_inc/header.php';
?>
<h2>Products</h2>
<?php if($msg=flash_get("success")) echo "<p class=\"card\">$msg</p>"; ?>
<a href="create.php" class="button">Create Product</a>
<form method="get" style="margin-top:20px;" class="card">
    <input type="text" name="q" placeholder="Search" value="<?php echo e($search); ?>">
    <select name="category_id">
        <option value="0">All Categories</option>
        <?php foreach ($cats as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php if($category_id==$c['id']) echo 'selected';?>><?php echo e($c['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="all" <?php if($status==='all') echo 'selected';?>>All</option>
        <option value="active" <?php if($status==='active') echo 'selected';?>>Active</option>
    </select>
    <select name="sort">
        <option value="created_desc" <?php if($sort==='created_desc') echo 'selected';?>>Newest</option>
        <option value="created_asc" <?php if($sort==='created_asc') echo 'selected';?>>Oldest</option>
    </select>
    <button type="submit">Filter</button>
</form>
<table class="table">
<tr><th>Title</th><th>Category</th><th>Price</th><th>Active</th><th>Featured</th><th>Created</th><th>Actions</th></tr>
<?php foreach ($products as $p): ?>
<tr>
    <td><?php echo e($p['title']); ?></td>
    <td><?php echo e($p['category_name']); ?></td>
    <td><?php echo e($p['price']); ?></td>
    <td><?php echo $p['is_active'] ? 'Yes':'No'; ?></td>
    <td><?php echo $p['is_featured'] ? 'Yes':'No'; ?></td>
    <td><?php echo e($p['created_at']); ?></td>
    <td>
        <a href="edit.php?id=<?php echo $p['id']; ?>">Edit</a>
        <form action="delete.php" method="post" style="display:inline;" onsubmit="return confirm('Delete?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
            <button type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
<div>
<?php for($i=1;$i<=$total_pages;$i++): ?>
    <a href="?page=<?php echo $i; ?>&q=<?php echo e($search); ?>&status=<?php echo e($status); ?>&category_id=<?php echo $category_id; ?>&sort=<?php echo e($sort); ?>" <?php if($page==$i) echo 'style="font-weight:bold"';?>><?php echo $i; ?></a>
<?php endfor; ?>
</div>
<?php include __DIR__ . '/../_inc/footer.php';
