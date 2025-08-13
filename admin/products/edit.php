<?php
// admin/products/edit.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

/* ---- Ensure we have $pdo ---- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $host = defined('DB_HOST') ? DB_HOST : ($DB_HOST ?? ($config['db']['host'] ?? '127.0.0.1'));
  $name = defined('DB_NAME') ? DB_NAME : ($DB_NAME ?? ($config['db']['name'] ?? 'u348991914_iswift'));
  $user = defined('DB_USER') ? DB_USER : ($DB_USER ?? ($config['db']['user'] ?? 'u348991914_iswift'));
  $pass = defined('DB_PASS') ? DB_PASS : ($DB_PASS ?? ($config['db']['pass'] ?? 'Z@q@@Fu|fQ$3'));
    if (function_exists('db')) {
        $maybe = db();
        if ($maybe instanceof PDO) {
            $pdo = $maybe;
        }
    }
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo "DB connection failed.";
            exit;
        }
    }
}

/* ---- Helpers ---- */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: uniqid('p-');
}
function ensure_upload_dir($dir)
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return is_writable($dir);
}
function save_image($file, $destDir)
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return [null, null];
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) finfo_close($finfo);
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
    if (!isset($allowed[$mime])) return [null, 'Invalid image type. Allowed: JPG, PNG, WEBP'];
    $name = uniqid('p_', true) . $allowed[$mime];
    $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return [null, 'Failed to move uploaded file'];
    return [$name, null];
}
function img_public_url($fname)
{
    return '/uploads/products/' . ltrim($fname, '/');
}

/* ---- Load product by slug ---- */
$slugParam = trim($_GET['slug'] ?? '');
if ($slugParam === '') {
    http_response_code(400);
    echo "Missing slug.";
    exit;
}

$prod = $pdo->prepare("SELECT * FROM products WHERE slug = :slug AND deleted_at IS NULL LIMIT 1");
$prod->execute([':slug' => $slugParam]);
$product = $prod->fetch();
if (!$product) {
    http_response_code(404);
    echo "Product not found.";
    exit;
}

$id = (int)$product['id'];

/* ---- Load related ---- */
$images = $pdo->prepare("SELECT id, path, is_primary, sort_order FROM product_images WHERE product_id=:id ORDER BY is_primary DESC, sort_order ASC, id ASC");
$images->execute([':id' => $id]);
$images = $images->fetchAll();

$specs = $pdo->prepare("SELECT id, label, value, sort_order FROM product_specs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$specs->execute([':id' => $id]);
$specs = $specs->fetchAll();

$features = $pdo->prepare("SELECT id, feature, sort_order FROM product_features WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$features->execute([':id' => $id]);
$features = $features->fetchAll();

$faqs = $pdo->prepare("SELECT id, question, answer, sort_order FROM product_faqs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$faqs->execute([':id' => $id]);
$faqs = $faqs->fetchAll();

/* ---- Defaults for form ---- */
$errors = [];
$success = false;

$vals = [
    'name' => $_POST['name'] ?? $product['name'],
    'slug' => $_POST['slug'] ?? $product['slug'],
    'sku'  => $_POST['sku'] ?? (string)$product['sku'],
    'short_desc' => $_POST['short_desc'] ?? (string)$product['short_desc'],
    'description' => $_POST['description'] ?? (string)$product['description'],
    'price' => $_POST['price'] ?? (string)$product['price'],
    'sale_price' => $_POST['sale_price'] ?? (string)$product['sale_price'],
    'status' => $_POST['status'] ?? $product['status'],
    'stock'  => $_POST['stock'] ?? (string)$product['stock'],
    'brochure_url' => $_POST['brochure_url'] ?? (string)$product['brochure_url'],
    'meta_title' => $_POST['meta_title'] ?? (string)$product['meta_title'],
    'meta_description' => $_POST['meta_description'] ?? (string)$product['meta_description'],
];

/* Arrays from POST (for replacement strategy) */
$spec_label = $_POST['spec_label'] ?? array_column($specs, 'label');
$spec_value = $_POST['spec_value'] ?? array_column($specs, 'value');
$feat_vals  = $_POST['features'] ?? array_column($features, 'feature');
$faq_q      = $_POST['faq_q'] ?? array_column($faqs, 'question');
$faq_a      = $_POST['faq_a'] ?? array_column($faqs, 'answer');

/* Image form inputs */
$delete_image_ids = isset($_POST['delete_image_ids']) ? array_map('intval', (array)$_POST['delete_image_ids']) : [];
$primary_choice   = isset($_POST['primary_choice']) ? (string)$_POST['primary_choice'] : ''; // 'existing_<id>' or 'new_primary' or '' (keep)

/* ---- Handle POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    if ($vals['name'] === '') $errors[] = 'Name is required.';
    if ($vals['price'] === '' || !is_numeric($vals['price']) || (float)$vals['price'] < 0) $errors[] = 'Price must be a non-negative number.';
    if ($vals['sale_price'] !== '' && (!is_numeric($vals['sale_price']) || (float)$vals['sale_price'] < 0)) $errors[] = 'Sale price must be a non-negative number.';
    if ($vals['stock'] === '' || !ctype_digit((string)$vals['stock']) || (int)$vals['stock'] < 0) $errors[] = 'Stock must be a non-negative integer.';
    if (!in_array($vals['status'], ['draft', 'published', 'archived'], true)) $errors[] = 'Invalid status.';

    // Slug handling (allow edit, ensure unique among others)
    $newSlug = trim($vals['slug']) !== '' ? trim($vals['slug']) : slugify($vals['name']);
    $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug AND id <> :id AND deleted_at IS NULL");
    $chk->execute([':slug' => $newSlug, ':id' => $id]);
    if ((int)$chk->fetchColumn() > 0) {
        $newSlug = $newSlug . '-' . substr(sha1(uniqid('', true)), 0, 6);
    }

    // Image upload dir
    $uploadBase = __DIR__ . '/../../uploads/products';
    if (!ensure_upload_dir($uploadBase)) $errors[] = 'Uploads directory is not writable: ' . h($uploadBase);

    // If no validation errors: apply changes in a transaction
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Update core product fields
            $upd = $pdo->prepare("
        UPDATE products SET
          name=:name, slug=:slug, sku=:sku, short_desc=:short_desc, description=:description,
          price=:price, sale_price=:sale_price, status=:status, stock=:stock,
          brochure_url=:brochure_url, meta_title=:meta_title, meta_description=:meta_description
        WHERE id=:id
        LIMIT 1
      ");
            $upd->execute([
                ':name' => $vals['name'],
                ':slug' => $newSlug,
                ':sku' => ($vals['sku'] !== '' ? $vals['sku'] : null),
                ':short_desc' => ($vals['short_desc'] !== '' ? $vals['short_desc'] : null),
                ':description' => ($vals['description'] !== '' ? $vals['description'] : null),
                ':price' => (float)$vals['price'],
                ':sale_price' => ($vals['sale_price'] !== '' ? (float)$vals['sale_price'] : null),
                ':status' => $vals['status'],
                ':stock' => (int)$vals['stock'],
                ':brochure_url' => ($vals['brochure_url'] !== '' ? $vals['brochure_url'] : null),
                ':meta_title' => ($vals['meta_title'] !== '' ? $vals['meta_title'] : null),
                ':meta_description' => ($vals['meta_description'] !== '' ? $vals['meta_description'] : null),
                ':id' => $id,
            ]);

            /* --- Images: delete selected, add new, set primary --- */
            // 1) Delete selected images (and files)
            if ($delete_image_ids) {
                // fetch file names for unlink
                $in = implode(',', array_fill(0, count($delete_image_ids), '?'));
                $sel = $pdo->prepare("SELECT id, path FROM product_images WHERE product_id=? AND id IN ($in)");
                $sel->execute(array_merge([$id], $delete_image_ids));
                $toDel = $sel->fetchAll();
                if ($toDel) {
                    $del = $pdo->prepare("DELETE FROM product_images WHERE product_id=? AND id IN ($in)");
                    $del->execute(array_merge([$id], $delete_image_ids));
                    foreach ($toDel as $r) {
                        $file = $uploadBase . DIRECTORY_SEPARATOR . $r['path'];
                        if (is_file($file)) @unlink($file);
                    }
                }
            }

            // 2) Add new primary (optional)
            $newPrimaryId = null;
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$fname, $err] = save_image($_FILES['primary_image'], $uploadBase);
                if ($err) {
                    throw new Exception('Primary image upload: ' . $err);
                }
                $ins = $pdo->prepare("INSERT INTO product_images (product_id, path, is_primary, sort_order) VALUES (:pid,:path,0,0)");
                $ins->execute([':pid' => $id, ':path' => $fname]);
                $newPrimaryId = (int)$pdo->lastInsertId();
            }

            // 3) Add new gallery images (optional)
            $newGalleryIds = [];
            if (!empty($_FILES['gallery_images']['name'][0])) {
                $count = count($_FILES['gallery_images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    $file = [
                        'name' => $_FILES['gallery_images']['name'][$i],
                        'type' => $_FILES['gallery_images']['type'][$i],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                        'error' => $_FILES['gallery_images']['error'][$i],
                        'size' => $_FILES['gallery_images']['size'][$i],
                    ];
                    [$fname, $err] = save_image($file, $uploadBase);
                    if ($err) {
                        throw new Exception('Gallery image ' . ($i + 1) . ' upload: ' . $err);
                    }
                    $ins = $pdo->prepare("INSERT INTO product_images (product_id, path, is_primary, sort_order) VALUES (:pid,:path,0,:ord)");
                    $ins->execute([':pid' => $id, ':path' => $fname, ':ord' => ($i + 1)]);
                    $newGalleryIds[] = (int)$pdo->lastInsertId();
                }
            }

            // 4) Primary selection logic
            // Options:
            //   - 'existing_<id>'     → set that existing image id primary
            //   - 'new_primary'       → set the newly uploaded primary as primary
            //   - '' (no change)      → keep current primary if exists
            if ($primary_choice) {
                // Clear current primary
                $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=:pid")->execute([':pid' => $id]);

                if (strpos($primary_choice, 'existing_') === 0) {
                    $pidImg = (int)substr($primary_choice, 9);
                    $pdo->prepare("UPDATE product_images SET is_primary=1 WHERE product_id=:pid AND id=:img")
                        ->execute([':pid' => $id, ':img' => $pidImg]);
                } elseif ($primary_choice === 'new_primary' && $newPrimaryId) {
                    $pdo->prepare("UPDATE product_images SET is_primary=1 WHERE product_id=:pid AND id=:img")
                        ->execute([':pid' => $id, ':img' => $newPrimaryId]);
                }
                // If 'new_primary' chosen but no file uploaded, nothing becomes primary;
                // you can re-select on next edit.
            }

            /* --- Specs: replace all for simplicity --- */
            $pdo->prepare("DELETE FROM product_specs WHERE product_id=:pid")->execute([':pid' => $id]);
            if ($spec_label && $spec_value) {
                $ps = $pdo->prepare("INSERT INTO product_specs (product_id,label,value,sort_order) VALUES (:pid,:l,:v,:o)");
                $ord = 1;
                for ($i = 0; $i < count($spec_label); $i++) {
                    $l = trim($spec_label[$i] ?? '');
                    $v = trim($spec_value[$i] ?? '');
                    if ($l === '' && $v === '') continue;
                    $ps->execute([':pid' => $id, ':l' => $l, ':v' => $v, ':o' => $ord++]);
                }
            }

            /* --- Features: replace all --- */
            $pdo->prepare("DELETE FROM product_features WHERE product_id=:pid")->execute([':pid' => $id]);
            if ($feat_vals) {
                $pf = $pdo->prepare("INSERT INTO product_features (product_id,feature,sort_order) VALUES (:pid,:f,:o)");
                $ord = 1;
                for ($i = 0; $i < count($feat_vals); $i++) {
                    $f = trim($feat_vals[$i] ?? '');
                    if ($f === '') continue;
                    $pf->execute([':pid' => $id, ':f' => $f, ':o' => $ord++]);
                }
            }

            /* --- FAQs: replace all --- */
            $pdo->prepare("DELETE FROM product_faqs WHERE product_id=:pid")->execute([':pid' => $id]);
            if ($faq_q && $faq_a) {
                $pq = $pdo->prepare("INSERT INTO product_faqs (product_id,question,answer,sort_order) VALUES (:pid,:q,:a,:o)");
                $ord = 1;
                for ($i = 0; $i < count($faq_q); $i++) {
                    $q = trim($faq_q[$i] ?? '');
                    $a = trim($faq_a[$i] ?? '');
                    if ($q === '' && $a === '') continue;
                    $pq->execute([':pid' => $id, ':q' => $q, ':a' => $a, ':o' => $ord++]);
                }
            }

            $pdo->commit();
            $success = true;

            // Refresh data after update
            $prod->execute([':slug' => $newSlug]);
            $product = $prod->fetch();
            $id = (int)$product['id'];

            $images = $pdo->prepare("SELECT id, path, is_primary, sort_order FROM product_images WHERE product_id=:id ORDER BY is_primary DESC, sort_order ASC, id ASC");
            $images->execute([':id' => $id]);
            $images = $images->fetchAll();

            $specs = $pdo->prepare("SELECT id, label, value, sort_order FROM product_specs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
            $specs->execute([':id' => $id]);
            $specs = $specs->fetchAll();

            $features = $pdo->prepare("SELECT id, feature, sort_order FROM product_features WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
            $features->execute([':id' => $id]);
            $features = $features->fetchAll();

            $faqs = $pdo->prepare("SELECT id, question, answer, sort_order FROM product_faqs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
            $faqs->execute([':id' => $id]);
            $faqs = $faqs->fetchAll();

            // update form defaults with new values
            $vals['slug'] = $product['slug'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Update failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin · Edit Product</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $BASE_URL ?>admin/assets/style.css">
</head>

<body class="sidebar-layout">
<?php include __DIR__ . '/../includes/nav.php'; ?>
<div class="main-content">
    <div class="container">
        <h1>Edit Product — <?= h($product['name']) ?></h1>

        <?php if ($errors): ?>
            <div class="errors">
                <strong>Fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                </ul>
            </div><br>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                Saved successfully.
                <div class="mt-6">
                    <a href="list.php">Back to list</a>
                    <span class="muted">·</span>
                    <a href="<?= $BASE_URL ?>product-details.php?slug=<?= h($product['slug']) ?>" target="_blank">View public page</a>
                </div>
            </div><br>
        <?php endif; ?>

        <form method="post" class="card" enctype="multipart/form-data">
            <!-- Core -->
            <h2>Core Details</h2>
            <div class="grid grid-2">
                <div>
                    <label for="name">Name *</label>
                    <input id="name" name="name" type="text" value="<?= h($vals['name']) ?>" required>
                </div>
                <div>
                    <label for="slug">Slug (unique)</label>
                    <input id="slug" name="slug" type="text" value="<?= h($vals['slug']) ?>">
                </div>
                <div>
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" value="<?= h($vals['sku']) ?>">
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= $vals['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $vals['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= $vals['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="price">Price (₹) *</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="<?= h($vals['price']) ?>" required>
                </div>
                <div>
                    <label for="sale_price">Sale Price (₹)</label>
                    <input id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="<?= h($vals['sale_price']) ?>">
                </div>
                <div>
                    <label for="stock">Stock *</label>
                    <input id="stock" name="stock" type="number" step="1" min="0" value="<?= h($vals['stock']) ?>" required>
                </div>
                <div>
                    <label for="brochure_url">Brochure URL</label>
                    <input id="brochure_url" name="brochure_url" type="text" value="<?= h($vals['brochure_url']) ?>">
                </div>
                <div>
                    <label for="meta_title">Meta Title (≤ 70)</label>
                    <input id="meta_title" name="meta_title" type="text" maxlength="70" value="<?= h($vals['meta_title']) ?>">
                </div>
                <div>
                    <label for="meta_description">Meta Description (≤ 160)</label>
                    <input id="meta_description" name="meta_description" type="text" maxlength="160" value="<?= h($vals['meta_description']) ?>">
                </div>
            </div>

              <div class="grid mt-16">
                <div>
                    <label for="short_desc">Short Description</label>
                    <textarea id="short_desc" name="short_desc"><?= h($vals['short_desc']) ?></textarea>
                </div>
                <div>
                    <label for="description">Full Description</label>
                    <textarea id="description" name="description"><?= h($vals['description']) ?></textarea>
                </div>
            </div>

            <!-- Images -->
            <h2>Images</h2>
            <div class="grid">
                <div>
                    <label>Existing Images</label>
                    <div class="image-grid">
                      <?php if (!$images): ?>
                        <div class="muted">No images yet.</div>
                      <?php else: foreach ($images as $img): ?>
                        <label class="image-card">
                          <img src="<?= h('/uploads/products/' . ltrim($img['path'], '/')) ?>"
                               alt=""
                               loading="lazy">
                          <div class="meta">#<?= (int)$img['id'] ?><?= $img['is_primary'] ? ' · Primary' : '' ?></div>
                          <div class="controls">
                            <label><input type="radio" name="primary_choice" value="existing_<?= (int)$img['id'] ?>" <?= $img['is_primary']?'checked':'' ?>> Primary</label>
                            <label><input type="checkbox" name="delete_image_ids[]" value="<?= (int)$img['id'] ?>"> Delete</label>
                          </div>
                        </label>
                      <?php endforeach; endif; ?>
                    </div>
                </div>
                <div>
                    <label for="primary_image">Upload New Primary</label>
                    <input id="primary_image" name="primary_image" type="file" accept=".jpg,.jpeg,.png,.webp">
                      <div class="muted mt-6">
                          If you want this new upload as primary, keep “Primary” set to <strong>“New primary upload”</strong> below.
                      </div>
                      <div class="mt-8">
                        <label><input type="radio" name="primary_choice" value="new_primary"> New primary upload</label>
                        <?php if ($images): ?>
                            <div class="muted">Or pick an existing image above as primary.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <label for="gallery_images">Add Gallery Images</label>
                    <input id="gallery_images" name="gallery_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
                </div>
                <p class="muted">Files are stored in <code>/uploads/products</code>. Deleting an image here removes the file from disk.</p>
            </div>

            <!-- Specs -->
            <h2>Specifications</h2>
            <div class="rep" id="specs">
                <?php
                $existing = max(count($spec_label), count($spec_value));
                if ($existing === 0) $existing = 1;
                for ($i = 0; $i < $existing; $i++):
                    $l = h($spec_label[$i] ?? '');
                    $v = h($spec_value[$i] ?? '');
                ?>
                    <div class="item">
                        <div class="row">
                            <div><label>Label</label><input type="text" name="spec_label[]" value="<?= $l ?>"></div>
                            <div><label>Value</label><input type="text" name="spec_value[]" value="<?= $v ?>"></div>
                        </div>
                        <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>
                    </div>
                <?php endfor; ?>
            </div>
              <div class="field-row"><button class="btn" type="button" onclick="addSpec()">+ Add Spec</button></div>

            <!-- Features -->
            <h2>Features (Bullets)</h2>
            <div class="rep" id="features">
                <?php if (!$feat_vals) $feat_vals = [''];
                foreach ($feat_vals as $f): $f = h($f); ?>
                    <div class="item">
                        <label>Feature</label>
                        <input type="text" name="features[]" value="<?= $f ?>">
                        <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>
                    </div>
                <?php endforeach; ?>
            </div>
              <div class="field-row"><button class="btn" type="button" onclick="addFeature()">+ Add Feature</button></div>

            <!-- FAQs -->
            <h2>FAQs</h2>
            <div class="rep" id="faqs">
                <?php
                $existing = max(count($faq_q), count($faq_a));
                if ($existing === 0) $existing = 1;
                for ($i = 0; $i < $existing; $i++):
                    $q = h($faq_q[$i] ?? '');
                    $a = h($faq_a[$i] ?? '');
                ?>
                    <div class="item">
                        <div class="row">
                            <div><label>Question</label><input type="text" name="faq_q[]" value="<?= $q ?>"></div>
                            <div><label>Answer</label><input type="text" name="faq_a[]" value="<?= $a ?>"></div>
                        </div>
                        <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>
                    </div>
                <?php endfor; ?>
            </div>
              <div class="field-row"><button class="btn" type="button" onclick="addFaq()">+ Add FAQ</button></div>

              <div class="mt-16">
                  <button class="btn" type="submit">Save Changes</button>
                  <a class="muted ml-12" href="list.php">Cancel</a>
              </div>
        </form>
    </div>
</div>

<script src="<?= $BASE_URL ?>admin/assets/nav.js"></script>

    <script>
        function removeClosest(btn, sel) {
            const el = btn.closest(sel);
            if (el) el.remove();
        }

        function addSpec() {
            const box = document.getElementById('specs');
            const div = document.createElement('div');
            div.className = 'item';
            div.innerHTML = `
    <div class="row">
      <div><label>Label</label><input type="text" name="spec_label[]" value=""></div>
      <div><label>Value</label><input type="text" name="spec_value[]" value=""></div>
    </div>
    <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>`;
            box.appendChild(div);
        }

        function addFeature() {
            const box = document.getElementById('features');
            const div = document.createElement('div');
            div.className = 'item';
            div.innerHTML = `
    <label>Feature</label>
    <input type="text" name="features[]" value="">
    <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>`;
            box.appendChild(div);
        }

        function addFaq() {
            const box = document.getElementById('faqs');
            const div = document.createElement('div');
            div.className = 'item';
            div.innerHTML = `
    <div class="row">
      <div><label>Question</label><input type="text" name="faq_q[]" value=""></div>
      <div><label>Answer</label><input type="text" name="faq_a[]" value=""></div>
    </div>
    <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>`;
            box.appendChild(div);
        }
    </script>
</body>

</html>