<?php
// product-details.php

/* ====== DB BOOTSTRAP (same robust block you okayed) ====== */
$pdo = $pdo ?? null;

$cfg = __DIR__ . '/admin/includes/config.php';
$dbf = __DIR__ . '/admin/includes/db.php';
if (is_file($cfg)) require_once $cfg;
if (is_file($dbf)) require_once $dbf;

if (!($pdo instanceof PDO)) {
  $host = defined('DB_HOST') ? DB_HOST : ($DB_HOST ?? ($config['db']['host'] ?? '127.0.0.1'));
  $name = defined('DB_NAME') ? DB_NAME : ($DB_NAME ?? ($config['db']['name'] ?? 'iswift_db'));
  $user = defined('DB_USER') ? DB_USER : ($DB_USER ?? ($config['db']['user'] ?? 'root'));
  $pass = defined('DB_PASS') ? DB_PASS : ($DB_PASS ?? ($config['db']['pass'] ?? ''));
  if (!$pdo && function_exists('db')) {
    $maybe = db();
    if ($maybe instanceof PDO) $pdo = $maybe;
  }
  if (!($pdo instanceof PDO)) {
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
/* ====== END DB BOOTSTRAP ====== */

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function price_html($price, $sale)
{
  $p = (float)$price;
  $s = $sale !== null ? (float)$sale : null;
  if ($s !== null && $s > 0 && $s < $p) {
    return '<span style="font-weight:700;">₹' . number_format($s) . '</span> <del style="opacity:.6;">₹' . number_format($p) . '</del> <span class="badge">Save ' . (int)round(100 * (1 - $s / $p)) . '%</span>';
  }
  return '<span style="font-weight:700;">₹' . number_format($p) . '</span>';
}
function img_url($path)
{
  $path = trim((string)$path);
  if ($path !== '') return '/uploads/products/' . ltrim($path, '/');
  return 'https://via.placeholder.com/900x675.png?text=Omvix+Product';
}

/* ====== INPUT ====== */
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
  http_response_code(400);
  echo "Missing product slug.";
  exit;
}

/* ====== FETCH PRODUCT ====== */
$sqlP = "SELECT id,name,slug,sku,short_desc,description,price,sale_price,stock,status,brochure_url,meta_title,meta_description,updated_at
         FROM products
         WHERE slug = :slug AND status = 'published' LIMIT 1";
$stmt = $pdo->prepare($sqlP);
$stmt->execute([':slug' => $slug]);
$product = $stmt->fetch();

if (!$product) {
  http_response_code(404);
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <title>Product not found</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
      body {
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        background: #FFF8F0;
        color: #1A1A1A;
        margin: 0
      }

      .wrap {
        max-width: 800px;
        margin: 10vh auto;
        padding: 24px
      }

      .card {
        background: #FFF1E5;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(255, 111, 64, .2);
        padding: 24px
      }

      a {
        color: #FF6F40;
        text-decoration: none;
        font-weight: 600
      }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  </head>

  <body>
    <div class="wrap">
      <div class="card">
        <h1 style="font-size:36px;margin:0 0 8px">Product not found</h1>
        <p style="color:#5A4033">We couldn’t find that item. It may be unpublished or the link is incorrect.</p>
        <p><a href="/products.php">← Back to Products</a></p>
      </div>
    </div>
  </body>

  </html>
<?php
  exit;
}

$id = (int)$product['id'];

/* ====== RELATED DATA ====== */
$imgs = $pdo->prepare("SELECT path,is_primary,sort_order,id FROM product_images WHERE product_id=:id ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgs->execute([':id' => $id]);
$images = $imgs->fetchAll();

$feats = $pdo->prepare("SELECT feature FROM product_features WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$feats->execute([':id' => $id]);
$features = $feats->fetchAll();

$specsS = $pdo->prepare("SELECT label,value FROM product_specs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$specsS->execute([':id' => $id]);
$specs = $specsS->fetchAll();

$faqsS = $pdo->prepare("SELECT question,answer FROM product_faqs WHERE product_id=:id ORDER BY sort_order ASC, id ASC");
$faqsS->execute([':id' => $id]);
$faqs = $faqsS->fetchAll();

/* ====== VIEW ====== */
$metaTitle = $product['meta_title'] ?: ($product['name'] . ' | Omvix');
$metaDesc  = $product['meta_description'] ?: ($product['short_desc'] ?: 'Omvix product detail page');
$primaryImg = $images[0]['path'] ?? ''; // thanks to ORDER BY is_primary desc
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= h($metaTitle) ?></title>
  <meta name="description" content="<?= h($metaDesc) ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-light: #FFF8F0;
      --accent-orange: #FF6F40;
      --accent-red: #E25822;
      --accent-yellow: #FFD447;
      --btn-bg: #FFB347;
      --btn-text: #3B1F0F;
      --text: #1A1A1A;
      --text-muted: #5A4033;
      --card: #FFF1E5;
      --shadow: 0 4px 20px rgba(255, 111, 64, .2);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg-light);
      color: var(--text);
      line-height: 1.5
    }

    a {
      color: var(--accent-orange);
      text-decoration: none
    }

    a:hover {
      text-decoration: underline
    }

    header,
    footer {
      background: var(--bg-light);
      padding: 24px 48px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap
    }

    header nav a {
      color: var(--accent-red);
      margin-left: 24px;
      font-weight: 600;
      text-decoration: none
    }

    header nav a:hover {
      color: var(--accent-orange)
    }

    .container {
      max-width: 1280px;
      margin: auto;
      padding: 48px 24px
    }

    .breadcrumb {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 16px
    }

    .grid {
      display: grid;
      gap: 32px
    }

    @media(min-width:960px) {
      .grid-2 {
        grid-template-columns: 1.2fr .8fr
      }
    }

    .card {
      background: var(--card);
      border-radius: 16px;
      box-shadow: var(--shadow);
      padding: 24px
    }

    .hero {
      width: 100%;
      aspect-ratio: 4/3;
      object-fit: cover;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .2);
      margin-bottom: 12px
    }

    .thumbs {
      display: flex;
      gap: 12px;
      flex-wrap: wrap
    }

    .thumbs img {
      width: 88px;
      height: 64px;
      object-fit: cover;
      border-radius: 10px;
      border: 2px solid transparent;
      cursor: pointer;
      transition: all .2s
    }

    .thumbs img.active,
    .thumbs img:hover {
      border-color: var(--accent-orange)
    }

    .price-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 8px 0 16px
    }

    .price {
      font-size: 28px;
      font-weight: 700
    }

    .badge {
      background: var(--accent-yellow);
      color: #3B1F0F;
      border-radius: 999px;
      padding: 6px 10px;
      font-weight: 700;
      font-size: 12px
    }

    .btn {
      background: var(--btn-bg);
      color: var(--btn-text);
      padding: 12px 20px;
      border: 0;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all .3s
    }

    .btn:hover {
      filter: brightness(110%);
      transform: translateY(-1px)
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      border: 1px solid var(--accent-orange)
    }

    th,
    td {
      padding: 12px;
      border: 1px solid var(--accent-orange);
      font-size: 16px;
      color: var(--text-muted)
    }

    th {
      background: var(--card);
      color: var(--accent-red);
      font-weight: 600;
      text-align: left
    }

    .faq {
      display: grid;
      gap: 12px;
      margin-top: 8px
    }

    .faq-item {
      border: 1px solid rgba(226, 88, 34, .3);
      border-radius: 12px;
      overflow: hidden;
      background: #fff
    }

    .faq-q {
      width: 100%;
      text-align: left;
      background: transparent;
      border: 0;
      padding: 14px 16px;
      font-weight: 600;
      color: var(--accent-red);
      cursor: pointer
    }

    .faq-a {
      padding: 0 16px 16px;
      color: var(--text-muted);
      display: none
    }

    .faq-item.open .faq-a {
      display: block
    }
  </style>
</head>

<body>
  <header>
    <a href="/iswift/"><strong style="color:var(--accent-red)">Omvix</strong></a>
    <nav>
      <a href="/products.php">Products</a>
      <a href="#">Solutions</a>
      <a href="#">Contact</a>
      <button class="btn">Book Demo</button>
    </nav>
  </header>

  <main class="container">
    <div class="breadcrumb">
      <a href="/iswift/products.php">Products</a> › <?= h($product['name']) ?>
    </div>

    <div class="grid grid-2">
      <!-- Gallery -->
      <section class="card">
        <?php $hero = img_url($primaryImg); ?>
        <img id="hero" class="hero" src="<?= h($hero) ?>" alt="<?= h($product['name']) ?>">
        <div class="thumbs">
          <?php foreach ($images as $i => $im): $src = img_url($im['path']); ?>
            <img src="<?= h($src) ?>" alt="thumb <?= $i + 1 ?>" data-full="<?= h($src) ?>" class="<?= $i === 0 ? 'active' : '' ?>">
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Summary -->
      <section class="card">
        <h1 style="font-size:40px;font-weight:700;margin-bottom:6px"><?= h($product['name']) ?></h1>
        <div class="sku" style="font-size:13px;color:var(--text-muted)">SKU: <?= h($product['sku']) ?></div>
        <p style="color:var(--text-muted);margin-top:8px"><?= h($product['short_desc']) ?></p>

        <div class="price-row">
          <div class="price"><?= price_html($product['price'], $product['sale_price']); ?></div>
          <?php if ((int)$product['stock'] > 0): ?>
            <span class="badge">In stock</span>
          <?php else: ?>
            <span class="badge" style="background:#ffd1d1;color:#8b0000">Out of stock</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($features)): ?>
          <ul style="list-style:none;display:grid;gap:8px;margin:12px 0">
            <?php foreach ($features as $f): ?>
              <li><span style="color:var(--accent-orange);font-weight:700">•</span> <?= h($f['feature']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px">
          <button class="btn">Enquire</button>
          <?php if (!empty($product['brochure_url'])): ?>
            <a class="btn" href="<?= h($product['brochure_url']) ?>" target="_blank" rel="noopener">Download Brochure</a>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- Description -->
    <?php if (!empty($product['description'])): ?>
      <section class="card" style="margin-top:24px">
        <h2 style="font-size:28px;font-weight:700;margin-bottom:8px;color:var(--accent-red)">Overview</h2>
        <div style="color:var(--text-muted)"><?= nl2br(h($product['description'])) ?></div>
      </section>
    <?php endif; ?>

    <!-- Specs -->
    <?php if (!empty($specs)): ?>
      <section class="card" style="margin-top:24px">
        <h2 style="font-size:28px;font-weight:700;margin-bottom:8px;color:var(--accent-red)">Specifications</h2>
        <table aria-label="Technical specifications">
          <tbody>
            <?php foreach ($specs as $s): ?>
              <tr>
                <th><?= h($s['label']) ?></th>
                <td><?= h($s['value']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <!-- FAQs -->
    <?php if (!empty($faqs)): ?>
      <section class="card" style="margin-top:24px">
        <h2 style="font-size:28px;font-weight:700;margin-bottom:8px;color:var(--accent-red)">FAQs</h2>
        <div class="faq">
          <?php foreach ($faqs as $fq): ?>
            <div class="faq-item">
              <button class="faq-q"><?= h($fq['question']) ?></button>
              <div class="faq-a"><?= nl2br(h($fq['answer'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section style="margin-top:24px">
      <a href="/iswift/products.php" class="btn">← Back to Products</a>
    </section>
  </main>

  <footer>
    <p style="width:100%;text-align:center;color:var(--text-muted);padding-top:24px;">© <?= date('Y') ?> Omvix. All rights reserved.</p>
  </footer>

  <script>
    // gallery
    const hero = document.getElementById('hero');
    document.querySelectorAll('.thumbs img').forEach(img => {
      img.addEventListener('click', () => {
        hero.src = img.dataset.full;
        document.querySelectorAll('.thumbs img').forEach(t => t.classList.remove('active'));
        img.classList.add('active');
      });
    });

    // faq
    document.querySelectorAll('.faq-q').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.parentElement.classList.toggle('open');
      });
    });
  </script>
</body>

</html>