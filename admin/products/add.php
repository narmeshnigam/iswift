<?php
// admin/products/add.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

/* ---- Ensure we have $pdo ---- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $host = defined('DB_HOST') ? DB_HOST : ($DB_HOST ?? ($config['db']['host'] ?? '127.0.0.1'));
  $name = defined('DB_NAME') ? DB_NAME : ($DB_NAME ?? ($config['db']['name'] ?? 'u348991914_iswift'));
  $user = defined('DB_USER') ? DB_USER : ($DB_USER ?? ($config['db']['user'] ?? 'u348991914_iswift'));
  $pass = defined('DB_PASS') ? DB_PASS : ($DB_PASS ?? ($config['db']['pass'] ?? 'Z@q@@Fu|fQ$3'));
  if (function_exists('db')) { $maybe = db(); if ($maybe instanceof PDO) { $pdo = $maybe; } }
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
      $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      http_response_code(500); echo "DB connection failed."; exit;
    }
  }
}

/* ---- Helpers ---- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify($text){
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-'); $text = preg_replace('~-+~', '-', $text); $text = strtolower($text);
  return $text ?: uniqid('p-');
}
function ensure_upload_dir($dir) {
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  if (!is_writable($dir)) { return false; }
  return true;
}
function save_image($file, $destDir) {
  if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return [null, null];
  $finfo = @finfo_open(FILEINFO_MIME_TYPE);
  $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
  if ($finfo) finfo_close($finfo);
  $allowed = ['image/jpeg'=>'.jpg', 'image/png'=>'.png', 'image/webp'=>'.webp'];
  if (!isset($allowed[$mime])) return [null, 'Invalid image type. Allowed: JPG, PNG, WEBP'];
  $name = uniqid('p_', true) . $allowed[$mime];
  $dest = rtrim($destDir,'/\\') . DIRECTORY_SEPARATOR . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return [null, 'Failed to move uploaded file'];
  return [$name, null];
}

/* ---- Defaults ---- */
$errors = [];
$successId = null;

$vals = [
  'name' => $_POST['name'] ?? '',
  'slug' => $_POST['slug'] ?? '',
  'sku'  => $_POST['sku'] ?? '',
  'short_desc' => $_POST['short_desc'] ?? '',
  'description' => $_POST['description'] ?? '',
  'price' => $_POST['price'] ?? '',
  'sale_price' => $_POST['sale_price'] ?? '',
  'status' => $_POST['status'] ?? 'draft',
  'stock'  => $_POST['stock'] ?? '0',
  'brochure_url' => $_POST['brochure_url'] ?? '',
  'meta_title' => $_POST['meta_title'] ?? '',
  'meta_description' => $_POST['meta_description'] ?? '',
];

/* Arrays for sub-sections */
$spec_label = $_POST['spec_label'] ?? [];
$spec_value = $_POST['spec_value'] ?? [];
$features   = $_POST['features'] ?? [];
$faq_q      = $_POST['faq_q'] ?? [];
$faq_a      = $_POST['faq_a'] ?? [];

/* ---- Handle POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Basic validation
  if ($vals['name'] === '') $errors[] = 'Name is required.';
  if ($vals['price'] === '' || !is_numeric($vals['price']) || (float)$vals['price'] < 0) $errors[] = 'Price must be a non-negative number.';
  if ($vals['sale_price'] !== '' && (!is_numeric($vals['sale_price']) || (float)$vals['sale_price'] < 0)) $errors[] = 'Sale price must be a non-negative number.';
  if ($vals['stock'] === '' || !ctype_digit((string)$vals['stock']) || (int)$vals['stock'] < 0) $errors[] = 'Stock must be a non-negative integer.';
  if (!in_array($vals['status'], ['draft','published','archived'], true)) $errors[] = 'Invalid status.';

  // Slug
  $slug = trim($vals['slug']) !== '' ? $vals['slug'] : slugify($vals['name']);

  // Prepare image dir
  $uploadBase = __DIR__ . '/../../uploads/products';
  $relBaseForDB = ''; // we store only file name; products list/detail prepend /uploads/products/
  if (!ensure_upload_dir($uploadBase)) {
    $errors[] = 'Uploads directory is not writable: ' . h($uploadBase);
  }

  // If no validation errors so far, start transaction
  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Ensure unique slug
      $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug AND deleted_at IS NULL");
      $chk->execute([':slug' => $slug]);
      if ((int)$chk->fetchColumn() > 0) {
        $slug = $slug . '-' . substr(sha1(uniqid('', true)), 0, 6);
      }

      // Insert product
      $stmt = $pdo->prepare("
        INSERT INTO products
          (name, slug, sku, short_desc, description, price, sale_price, status, stock, brochure_url, meta_title, meta_description)
        VALUES
          (:name, :slug, :sku, :short_desc, :description, :price, :sale_price, :status, :stock, :brochure_url, :meta_title, :meta_description)
      ");
      $stmt->execute([
        ':name' => $vals['name'],
        ':slug' => $slug,
        ':sku'  => ($vals['sku'] !== '' ? $vals['sku'] : null),
        ':short_desc' => ($vals['short_desc'] !== '' ? $vals['short_desc'] : null),
        ':description' => ($vals['description'] !== '' ? $vals['description'] : null),
        ':price' => (float)$vals['price'],
        ':sale_price' => ($vals['sale_price'] !== '' ? (float)$vals['sale_price'] : null),
        ':status' => $vals['status'],
        ':stock'  => (int)$vals['stock'],
        ':brochure_url' => ($vals['brochure_url'] !== '' ? $vals['brochure_url'] : null),
        ':meta_title' => ($vals['meta_title'] !== '' ? $vals['meta_title'] : null),
        ':meta_description' => ($vals['meta_description'] !== '' ? $vals['meta_description'] : null),
      ]);
      $pid = (int)$pdo->lastInsertId();

      /* Images: primary + gallery[] (optional) */
      $imagesToInsert = [];
      if (isset($_FILES['primary_image'])) {
        [$fname, $err] = save_image($_FILES['primary_image'], $uploadBase);
        if ($err) $errors[] = 'Primary image: ' . $err;
        if ($fname) $imagesToInsert[] = ['path'=>$fname, 'is_primary'=>1, 'sort_order'=>0];
      }
      if (!empty($_FILES['gallery_images']['name'][0])) {
        $count = count($_FILES['gallery_images']['name']);
        for ($i=0; $i<$count; $i++) {
          $file = [
            'name' => $_FILES['gallery_images']['name'][$i],
            'type' => $_FILES['gallery_images']['type'][$i],
            'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
            'error' => $_FILES['gallery_images']['error'][$i],
            'size' => $_FILES['gallery_images']['size'][$i],
          ];
          [$fname, $err] = save_image($file, $uploadBase);
          if ($err) $errors[] = 'Gallery image '.($i+1).': ' . $err;
          if ($fname) $imagesToInsert[] = ['path'=>$fname, 'is_primary'=>0, 'sort_order'=>($i+1)];
        }
      }

      if ($errors) { $pdo->rollBack(); throw new Exception('Validation after upload'); }

      if ($imagesToInsert) {
        $si = $pdo->prepare("INSERT INTO product_images (product_id, path, is_primary, sort_order) VALUES (:pid,:path,:prim,:ord)");
        foreach ($imagesToInsert as $img) {
          $si->execute([
            ':pid'=>$pid, ':path'=>$img['path'], ':prim'=>$img['is_primary'], ':ord'=>$img['sort_order']
          ]);
        }
      }

      /* Specs (label/value arrays) */
      if ($spec_label && $spec_value) {
        $ss = $pdo->prepare("INSERT INTO product_specs (product_id, label, value, sort_order) VALUES (:pid,:label,:value,:ord)");
        $ord = 1;
        for ($i=0; $i<count($spec_label); $i++) {
          $l = trim($spec_label[$i] ?? '');
          $v = trim($spec_value[$i] ?? '');
          if ($l === '' && $v === '') continue;
          $ss->execute([':pid'=>$pid, ':label'=>$l, ':value'=>$v, ':ord'=>$ord++]);
        }
      }

      /* Features (bullets) */
      if ($features) {
        $sf = $pdo->prepare("INSERT INTO product_features (product_id, feature, sort_order) VALUES (:pid,:feature,:ord)");
        $ord = 1;
        for ($i=0; $i<count($features); $i++) {
          $f = trim($features[$i] ?? '');
          if ($f === '') continue;
          $sf->execute([':pid'=>$pid, ':feature'=>$f, ':ord'=>$ord++]);
        }
      }

      /* FAQs (q/a) */
      if ($faq_q && $faq_a) {
        $fq = $pdo->prepare("INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (:pid,:q,:a,:ord)");
        $ord = 1;
        for ($i=0; $i<count($faq_q); $i++) {
          $q = trim($faq_q[$i] ?? '');
          $a = trim($faq_a[$i] ?? '');
          if ($q === '' && $a === '') continue;
          $fq->execute([':pid'=>$pid, ':q'=>$q, ':a'=>$a, ':ord'=>$ord++]);
        }
      }

      $pdo->commit();
      $successId = $pid;

      // Reset form after success (keep minimal defaults)
      foreach ($vals as $k=>$_) $vals[$k] = '';
      $vals['status'] = 'draft'; $vals['stock'] = '0';
      $spec_label = $spec_value = $features = $faq_q = $faq_a = [];

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Insert failed.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin · Add Product</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $BASE_URL ?>admin/assets/style.css">
</head>
<body class="sidebar-layout">
<?php include __DIR__ . '/../includes/nav.php'; ?>
<div class="main-content">
  <div class="container">
    <h1>Add Product (All-in-one)</h1>
  <?php if ($errors): ?>
    <div class="errors">
      <strong>Fix the following:</strong>
      <ul>
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div><br>
  <?php endif; ?>

  <?php if ($successId): ?>
    <div class="success">
      Product created successfully.
      <div class="mt-6">
        <a href="edit.php?id=<?= (int)$successId ?>">Edit this product</a>
        <span class="muted">·</span>
        <a href="list.php">Back to list</a>
        <span class="muted">·</span>
        <a href="<?= $BASE_URL ?>product-details.php?slug=<?= h($slug ?? '') ?>" target="_blank">View public page</a>
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
        <label for="slug">Slug (auto if blank)</label>
        <input id="slug" name="slug" type="text" value="<?= h($vals['slug']) ?>">
      </div>
      <div>
        <label for="sku">SKU</label>
        <input id="sku" name="sku" type="text" value="<?= h($vals['sku']) ?>">
      </div>
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="draft"     <?= $vals['status']==='draft'?'selected':'' ?>>Draft</option>
          <option value="published" <?= $vals['status']==='published'?'selected':'' ?>>Published</option>
          <option value="archived"  <?= $vals['status']==='archived'?'selected':'' ?>>Archived</option>
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
        <label for="primary_image">Primary Image (JPG/PNG/WEBP)</label>
        <input id="primary_image" name="primary_image" type="file" accept=".jpg,.jpeg,.png,.webp">
      </div>
      <div>
        <label for="gallery_images">Gallery Images (multiple allowed)</label>
        <input id="gallery_images" name="gallery_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
      </div>
      <p class="muted">Files will be stored in <code>/uploads/products</code>. First is saved as primary (if provided), gallery will be ordered as selected.</p>
    </div>

    <!-- Specs -->
    <h2>Specifications</h2>
    <div class="rep" id="specs">
      <!-- items injected by JS or preserved from POST -->
      <?php
      $existing = max(count($spec_label), count($spec_value));
      for ($i=0; $i<$existing; $i++):
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
    <div class="inline field-row">
      <button class="btn" type="button" onclick="addSpec()">+ Add Spec</button>
    </div>

    <!-- Features -->
    <h2>Features (Bullets)</h2>
    <div class="rep" id="features">
      <?php foreach ($features as $f): $f=h($f); ?>
      <div class="item">
        <label>Feature</label>
        <input type="text" name="features[]" value="<?= $f ?>">
        <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="inline field-row">
      <button class="btn" type="button" onclick="addFeature()">+ Add Feature</button>
    </div>

    <!-- FAQs -->
    <h2>FAQs</h2>
    <div class="rep" id="faqs">
      <?php
      $existing = max(count($faq_q), count($faq_a));
      for ($i=0; $i<$existing; $i++):
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
    <div class="inline field-row">
      <button class="btn" type="button" onclick="addFaq()">+ Add FAQ</button>
    </div>

    <div class="mt-16">
      <button class="btn" type="submit">Create Product</button>
      <a class="muted ml-12" href="list.php">Cancel</a>
    </div>
  </form>
    </div>
  </div>

<script src="<?= $BASE_URL ?>admin/assets/nav.js"></script>

<script>
function removeClosest(btn, sel){ const el = btn.closest(sel); if(el) el.remove(); }
function addSpec(){
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
function addFeature(){
  const box = document.getElementById('features');
  const div = document.createElement('div');
  div.className = 'item';
  div.innerHTML = `
    <label>Feature</label>
    <input type="text" name="features[]" value="">
    <div class="actions"><button class="btn" type="button" onclick="removeClosest(this,'.item')">Remove</button></div>`;
  box.appendChild(div);
}
function addFaq(){
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
