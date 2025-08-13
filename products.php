<?php
// products.php

/* ====== DB BOOTSTRAP (robust) ====== */
$pdo = $pdo ?? null;

/* 1) Try to include your admin config/db first */
$cfg = __DIR__ . '/admin/includes/config.php';
$dbf = __DIR__ . '/admin/includes/db.php';
if (is_file($cfg)) require_once $cfg;
if (is_file($dbf)) require_once $dbf;

/* 2) If $pdo already provided by admin code, use it */
if ($pdo instanceof PDO) {
  // ok
} else {
  /* 3) Collect credentials from common patterns */
  $host = null;
  $name = null;
  $user = null;
  $pass = null;

  // a) Constants
  if (defined('DB_HOST')) $host = DB_HOST;
  if (defined('DB_NAME')) $name = DB_NAME;
  if (defined('DB_USER')) $user = DB_USER;
  if (defined('DB_PASS')) $pass = DB_PASS;

  // b) Variables like $DB_HOST etc.
  if (!$host && isset($DB_HOST)) $host = $DB_HOST;
  if (!$name && isset($DB_NAME)) $name = $DB_NAME;
  if (!$user && isset($DB_USER)) $user = $DB_USER;
  if (!$pass && isset($DB_PASS)) $pass = $DB_PASS;

  // c) Array config e.g. $config['db']['host']
  if (isset($config['db'])) {
    $host = $host ?: ($config['db']['host'] ?? null);
    $name = $name ?: ($config['db']['name'] ?? null);
    $user = $user ?: ($config['db']['user'] ?? null);
    $pass = $pass ?: ($config['db']['pass'] ?? null);
  }

  // d) Fallback to typical local defaults if nothing found
  $host = $host ?: '127.0.0.1';
  $name = $name ?: 'iswift_db';
  $user = $user ?: 'root';
  $pass = $pass ?? '';

  /* 4) Build PDO (last resort). If your admin exposes a helper like db(), use it */
  if (!$pdo && function_exists('db')) {
    $maybe = db(); // some projects return a PDO here
    if ($maybe instanceof PDO) $pdo = $maybe;
  }

  if (!$pdo) {
    try {
      $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo "DB connection failed.";
      // Uncomment next line temporarily if you want the actual reason while debugging:
      // echo "<pre>".$e->getMessage()."</pre>";
      exit;
    }
  }
}
/* ====== END DB BOOTSTRAP ====== */


/* ====== INPUTS ====== */
$q         = trim($_GET['q'] ?? '');
$min       = trim($_GET['min'] ?? '');
$max       = trim($_GET['max'] ?? '');
$on_sale   = isset($_GET['on_sale']) ? 1 : 0;
$in_stock  = isset($_GET['in_stock']) ? 1 : 0;
$sort      = $_GET['sort'] ?? 'latest'; // latest | price_asc | price_desc | name_asc | name_desc
$per_page  = (int)($_GET['per_page'] ?? 12);
$page      = max(1, (int)($_GET['page'] ?? 1));

$per_page = in_array($per_page, [12, 24, 48], true) ? $per_page : 12;
$offset   = ($page - 1) * $per_page;

/* ====== QUERY BUILDER ====== */
$where = ["p.status = 'published'"];
$params = [];

if ($q !== '') {
  $where[] = "(p.name LIKE :q OR p.short_desc LIKE :q OR p.sku LIKE :q)";
  $params[':q'] = "%{$q}%";
}
if ($min !== '' && is_numeric($min)) {
  $where[] = "COALESCE(p.sale_price, p.price) >= :minp";
  $params[':minp'] = (float)$min;
}
if ($max !== '' && is_numeric($max)) {
  $where[] = "COALESCE(p.sale_price, p.price) <= :maxp";
  $params[':maxp'] = (float)$max;
}
if ($on_sale) {
  $where[] = "p.sale_price IS NOT NULL";
}
if ($in_stock) {
  $where[] = "p.stock > 0";
}

$order = "p.updated_at DESC";
switch ($sort) {
  case 'price_asc':
    $order = "COALESCE(p.sale_price, p.price) ASC";
    break;
  case 'price_desc':
    $order = "COALESCE(p.sale_price, p.price) DESC";
    break;
  case 'name_asc':
    $order = "p.name ASC";
    break;
  case 'name_desc':
    $order = "p.name DESC";
    break;
  case 'latest':
  default:
    $order = "p.updated_at DESC";
}

$wsql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* ====== COUNT ====== */
$countSql = "SELECT COUNT(*) AS cnt
             FROM products p
             $wsql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

/* ====== DATA: with primary image (fallback to any) ====== */
$sql = "
SELECT
  p.id, p.name, p.slug, p.sku, p.short_desc, p.price, p.sale_price, p.stock, p.updated_at,
  COALESCE(pim.path, pia.path) AS image_path
FROM products p
LEFT JOIN (
  SELECT product_id, path
  FROM product_images
  WHERE is_primary = 1
  GROUP BY product_id
) pim ON pim.product_id = p.id
LEFT JOIN (
  SELECT pi2.product_id, pi2.path
  FROM product_images pi2
  INNER JOIN (
    SELECT product_id, MIN(id) AS min_id
    FROM product_images
    GROUP BY product_id
  ) x ON x.product_id = pi2.product_id AND x.min_id = pi2.id
) pia ON pia.product_id = p.id
$wsql
ORDER BY $order
LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* ====== HELPERS ====== */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function price_html($price, $sale)
{
  if ($sale !== null && $sale !== '' && (float)$sale > 0 && (float)$sale < (float)$price) {
    return '<span style="font-weight:600;">₹' . number_format((float)$sale) . '</span> <del style="opacity:.6;">₹' . number_format((float)$price) . '</del>';
  }
  return '₹' . number_format((float)$price);
}
function img_url($path)
{
  $path = trim((string)$path);
  if ($path !== '') {
    // Adjust the base path if your images live elsewhere
    return '/uploads/products/' . ltrim($path, '/');
  }
  // Brand-friendly placeholder
  return 'https://via.placeholder.com/600x400.png?text=Omvix+Product';
}

// Preserve query params for pagination/sort
function qp($overrides = [])
{
  $qs = array_merge($_GET, $overrides);
  return '?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Products | Omvix</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Inter font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-light: #FFF8F0;
      --accent-orange: #FF6F40;
      --accent-red: #E25822;
      --accent-yellow: #FFD447;
      --btn-bg: #FFB347;
      --btn-text: #3B1F0F;
      --text-main: #1A1A1A;
      --text-muted: #5A4033;
      --card-bg: #FFF1E5;
      --shadow-tint: rgba(255, 111, 64, 0.2);
      --font-family: 'Inter', sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background: var(--bg-light);
      color: var(--text-main);
      font-family: var(--font-family);
      line-height: 1.5;
    }

    header,
    footer {
      background: var(--bg-light);
      padding: 24px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    header nav a {
      color: var(--accent-red);
      margin-left: 24px;
      font-weight: 600;
      text-decoration: none;
    }

    header nav a:hover {
      color: var(--accent-orange);
    }

    .container {
      max-width: 1280px;
      margin: auto;
      padding: 48px 24px;
    }

    h1 {
      font-size: 40px;
      font-weight: 700;
      margin-bottom: 16px;
    }

    .muted {
      color: var(--text-muted);
    }

    .filters {
      background: var(--card-bg);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 4px 20px var(--shadow-tint);
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      align-items: end;
      margin-top: 16px;
    }

    label {
      display: block;
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    input[type="text"],
    input[type="number"],
    select {
      width: 100%;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid var(--accent-orange);
      background: transparent;
      color: var(--text-muted);
    }

    .btn {
      background: var(--btn-bg);
      color: var(--btn-text);
      padding: 12px 20px;
      border-radius: 12px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all .3s ease;
    }

    .btn:hover {
      filter: brightness(110%);
      transform: scale(1.02);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
      margin-top: 24px;
    }

    .card {
      background: var(--card-bg);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 4px 20px var(--shadow-tint);
      transition: transform .3s ease, box-shadow .3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 24px rgba(255, 111, 64, .3);
    }

    .card img {
      width: 100%;
      border-radius: 16px;
      margin-bottom: 12px;
      display: block;
    }

    .price {
      font-size: 18px;
      margin-top: 4px;
    }

    .sku {
      font-size: 13px;
      color: var(--text-muted);
    }

    .stock-ok {
      font-size: 13px;
      color: #0f7a3d;
      font-weight: 600;
    }

    .stock-out {
      font-size: 13px;
      color: #b00020;
      font-weight: 600;
    }

    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 16px;
      gap: 16px;
      flex-wrap: wrap;
    }

    .pill {
      background: #FFEAD8;
      color: var(--accent-red);
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 13px;
    }

    .pagination {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: center;
      margin: 24px 0;
      flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
      padding: 8px 12px;
      border: 1px solid var(--accent-orange);
      border-radius: 8px;
      text-decoration: none;
      color: var(--accent-red);
      font-weight: 600;
    }

    .pagination .active {
      background: var(--accent-orange);
      color: #fff;
      border-color: var(--accent-orange);
    }

    .tag {
      font-size: 12px;
      background: #fff;
      border: 1px dashed var(--accent-orange);
      padding: 4px 8px;
      border-radius: 999px;
      margin-left: 8px;
    }
  </style>
</head>

<body>
  <header>
    <a href="/"><strong>iSwift</strong></a>
    <nav>
      <a href="/">Home</a>
      <a href="<?= h(basename(__FILE__)) ?>">Products</a>
      <a href="#">Contact</a>
      <a class="btn" href="#">Book Demo</a>
    </nav>
  </header>

  <main class="container">
    <h1>Products</h1>
    <div class="toolbar">
      <div class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> found</div>
      <div>
        <span class="pill">Sort:</span>
        <a class="tag" href="<?= h(qp(['sort' => 'latest', 'page' => 1])) ?>">Latest</a>
        <a class="tag" href="<?= h(qp(['sort' => 'price_asc', 'page' => 1])) ?>">Price ↑</a>
        <a class="tag" href="<?= h(qp(['sort' => 'price_desc', 'page' => 1])) ?>">Price ↓</a>
        <a class="tag" href="<?= h(qp(['sort' => 'name_asc', 'page' => 1])) ?>">A–Z</a>
        <a class="tag" href="<?= h(qp(['sort' => 'name_desc', 'page' => 1])) ?>">Z–A</a>
      </div>
    </div>

    <form class="filters" method="get" action="">
      <div>
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="Name, SKU, description">
      </div>
      <div>
        <label for="min">Min Price (₹)</label>
        <input type="number" id="min" name="min" min="0" step="100" value="<?= h($min) ?>">
      </div>
      <div>
        <label for="max">Max Price (₹)</label>
        <input type="number" id="max" name="max" min="0" step="100" value="<?= h($max) ?>">
      </div>
      <div>
        <label for="sort">Sort by</label>
        <select id="sort" name="sort">
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
          <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
        </select>
      </div>
      <div>
        <label for="per_page">Per page</label>
        <select id="per_page" name="per_page">
          <option value="12" <?= $per_page === 12 ? 'selected' : '' ?>>12</option>
          <option value="24" <?= $per_page === 24 ? 'selected' : '' ?>>24</option>
          <option value="48" <?= $per_page === 48 ? 'selected' : '' ?>>48</option>
        </select>
      </div>
      <div style="display:flex; gap:12px; align-items:center;">
        <div>
          <input type="checkbox" id="on_sale" name="on_sale" <?= $on_sale ? 'checked' : '' ?>>
          <label for="on_sale">On Sale</label>
        </div>
        <div>
          <input type="checkbox" id="in_stock" name="in_stock" <?= $in_stock ? 'checked' : '' ?>>
          <label for="in_stock">In Stock</label>
        </div>
      </div>
      <div>
        <button class="btn" type="submit">Apply</button>
        <a class="btn" href="<?= h(basename(__FILE__)) ?>" style="margin-left:8px;">Reset</a>
      </div>
    </form>

    <section class="grid">
      <?php if (!$rows): ?>
        <p class="muted">No products match your filters.</p>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <article class="card">
            <a href="/iswift/product-details.php?slug=<?= h($r['slug']) ?>">
              <img src="<?= h(img_url($r['image_path'])) ?>" alt="<?= h($r['name']) ?>" loading="lazy">
              <h3 style="font-size:22px; font-weight:600;"><?= h($r['name']) ?></h3>
            </a>
            <div class="sku">SKU: <?= h($r['sku']) ?></div>
            <p class="muted" style="margin-top:8px;"><?= h($r['short_desc']) ?></p>
            <div class="price"><?= price_html($r['price'], $r['sale_price']); ?></div>
            <div style="margin-top:6px;">
              <?= ($r['stock'] > 0) ? '<span class="stock-ok">In stock</span>' : '<span class="stock-out">Out of stock</span>'; ?>
            </div>
            <div style="margin-top:12px;">
              <a class="btn" href="/iswift/product-details.php?slug=<?= h($r['slug']) ?>">View Details</a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Pagination">
        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        if ($page > 1) {
          echo '<a href="' . h(qp(['page' => 1])) . '">« First</a>';
          echo '<a href="' . h(qp(['page' => $page - 1])) . '">‹ Prev</a>';
        }
        for ($p = $start; $p <= $end; $p++) {
          if ($p == $page) {
            echo '<span class="active">' . $p . '</span>';
          } else {
            echo '<a href="' . h(qp(['page' => $p])) . '">' . $p . '</a>';
          }
        }
        if ($page < $pages) {
          echo '<a href="' . h(qp(['page' => $page + 1])) . '">Next ›</a>';
          echo '<a href="' . h(qp(['page' => $pages])) . '">Last »</a>';
        }
        ?>
      </nav>
    <?php endif; ?>
  </main>

  <footer>
    <p style="width:100%; text-align:center; color:var(--text-muted); padding-top:24px;">© <?= date('Y') ?> Omvix. All rights reserved.</p>
  </footer>
</body>

</html>