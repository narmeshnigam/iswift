<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

$errors = [];
$cats = db()->query('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $title = input('title');
    $slug = input('slug');
    $slug = $slug ? slugify($slug) : slugify($title);
    // ensure unique slug
    $slug = unique_slug($slug);
    $sku = input('sku');
    $category_id = (int)input('category_id');
    $price = (float)input('price');
    $sale_price = (float)input('sale_price');
    $short_desc = input('short_desc');
    $description = input('description');
    $is_active = input('is_active') ? 1 : 0;
    $is_featured = input('is_featured') ? 1 : 0;
    $meta_title = input('meta_title');
    $meta_desc = input('meta_desc');
    $images = [input('image1'),input('image2'),input('image3')];

    if ($title==='') $errors['title']='Title required';
    if (strlen($short_desc)>300) $errors['short_desc']='Short description too long';

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO products (title,slug,sku,category_id,price,sale_price,short_desc,description,is_active,is_featured,meta_title,meta_desc) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$title,$slug,$sku,$category_id,$price,$sale_price,$short_desc,$description,$is_active,$is_featured,$meta_title,$meta_desc]);
        $pid = (int)db()->lastInsertId();
        $i=1;
        foreach ($images as $url) {
            if ($url) {
                $stmt2=db()->prepare('INSERT INTO product_images (product_id,url,sort_order) VALUES (?,?,?)');
                $stmt2->execute([$pid,$url,$i]);
            }
            $i++;
        }
        flash_set('success','Product created');
        redirect('list.php');
    }
}

function old($key){ return e(input($key)); }

include __DIR__ . '/../_inc/header.php';
?>
<h2>Create Product</h2>
<form method="post" class="card">
    <?php echo csrf_field(); ?>
    <label>Title
        <input type="text" name="title" value="<?php echo old('title'); ?>" required>
        <?php if(!empty($errors['title'])) echo '<div class="form-error">'.e($errors['title']).'</div>'; ?>
    </label>
    <label>Slug
        <input type="text" name="slug" value="<?php echo old('slug'); ?>">
    </label>
    <label>SKU
        <input type="text" name="sku" value="<?php echo old('sku'); ?>">
    </label>
    <label>Category
        <select name="category_id">
            <?php foreach($cats as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php if((int)input('category_id')==$c['id']) echo 'selected';?>><?php echo e($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Price
        <input type="text" name="price" value="<?php echo old('price'); ?>">
    </label>
    <label>Sale Price
        <input type="text" name="sale_price" value="<?php echo old('sale_price'); ?>">
    </label>
    <label>Short Description
        <textarea name="short_desc" maxlength="300"><?php echo old('short_desc'); ?></textarea>
        <?php if(!empty($errors['short_desc'])) echo '<div class="form-error">'.e($errors['short_desc']).'</div>'; ?>
    </label>
    <label>Description
        <textarea name="description"><?php echo old('description'); ?></textarea>
    </label>
    <label><input type="checkbox" name="is_active" value="1" <?php if(input('is_active')) echo 'checked';?>> Active</label>
    <label><input type="checkbox" name="is_featured" value="1" <?php if(input('is_featured')) echo 'checked';?>> Featured</label>
    <label>Meta Title
        <input type="text" name="meta_title" value="<?php echo old('meta_title'); ?>">
    </label>
    <label>Meta Description
        <textarea name="meta_desc"><?php echo old('meta_desc'); ?></textarea>
    </label>
    <label>Image URL 1
        <input type="text" name="image1" value="<?php echo old('image1'); ?>">
    </label>
    <label>Image URL 2
        <input type="text" name="image2" value="<?php echo old('image2'); ?>">
    </label>
    <label>Image URL 3
        <input type="text" name="image3" value="<?php echo old('image3'); ?>">
    </label>
    <input type="submit" value="Save">
</form>
<?php include __DIR__ . '/../_inc/footer.php';

function unique_slug(string $slug, int $exclude_id=0): string {
    $base = $slug; $i=1;
    while(true){
        $params=[$slug];
        $sql='SELECT COUNT(*) FROM products WHERE slug=?';
        if($exclude_id){$sql.=' AND id<>?';$params[]=$exclude_id;}
        $stmt=db()->prepare($sql);
        $stmt->execute($params);
        if($stmt->fetchColumn()==0) break;
        $slug = $base.'-'.$i; $i++;
    }
    return $slug;
}
