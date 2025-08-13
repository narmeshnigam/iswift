<?php
// admin/products/list.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php'; // ensure constants are loaded
require_once __DIR__ . '/../includes/db.php';     // may or may not set $pdo

// Ensure we have a PDO instance in $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
  // Try common credential sources
  $host = defined('DB_HOST') ? DB_HOST : ($DB_HOST ?? ($config['db']['host'] ?? '127.0.0.1'));
  $name = defined('DB_NAME') ? DB_NAME : ($DB_NAME ?? ($config['db']['name'] ?? 'u348991914_iswift'));
  $user = defined('DB_USER') ? DB_USER : ($DB_USER ?? ($config['db']['user'] ?? 'u348991914_iswift'));
  $pass = defined('DB_PASS') ? DB_PASS : ($DB_PASS ?? ($config['db']['pass'] ?? 'Z@q@@Fu|fQ$3'));

  // If your db.php exposes a helper like db(), use it
  if (function_exists('db')) {
    $maybe = db();
    if ($maybe instanceof PDO) {
      $pdo = $maybe;
    }
  }

  if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
      $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
      $pdo = new PDO($dsn, $user, $pass, [
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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin · Products</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $BASE_URL ?>admin/assets/style.css">
</head>

<body class="sidebar-layout">
<?php include __DIR__ . '/../includes/nav.php'; ?>
<div class="main-content">
    <div class="container">
        <h1>Products</h1>

        <?php
        // 2) Inputs (sanitized)
        $q       = isset($_GET['q']) ? trim($_GET['q']) : '';
        $status  = isset($_GET['status']) ? $_GET['status'] : '';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(5, (int)($_GET['per'] ?? 10)));

        $sort    = $_GET['sort'] ?? 'updated_at';
        $dir     = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // Whitelist sortable columns
        $sortable = [
            'name' => 'name',
            'sku' => 'sku',
            'price' => 'price',
            'sale_price' => 'sale_price',
            'stock' => 'stock',
            'status' => 'status',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];
        $orderBy = $sortable[$sort] ?? 'updated_at';

        // 3) Build WHERE + params
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[] = '(name LIKE :q OR sku LIKE :q OR meta_title LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // 4) Count for pagination
        $countSql = "SELECT COUNT(*) FROM products $whereSql";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $pages = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // 5) Fetch rows
        $sql = "
  SELECT id, name, slug, sku, price, sale_price, status, stock, updated_at
  FROM products
  $whereSql
  ORDER BY $orderBy $dir
  LIMIT :limit OFFSET :offset
";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Helper to keep query string on links
        function keep(array $extra = [])
        {
            $qs = array_merge($_GET, $extra);
            return '?' . http_build_query($qs);
        }
        ?>

        <!-- 6) Toolbar -->
        <form class="filter-bar" method="get">
            <div>
                <input class="search-input" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name, SKU, meta title">
                <select class="filter-select" name="status">
                    <option value="">All statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
                <select class="filter-select" name="per">
                    <?php foreach ([10, 20, 30, 50] as $n): ?>
                        <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?>/page</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Apply</button>
                <?php if ($q !== '' || $status !== ''): ?>
                    <a class="muted" href="list.php">Reset</a>
                <?php endif; ?>
            </div>

            <div class="right">
                <a class="btn" href="add.php">+ Add Product</a>
            </div>
        </form>

        <!-- 7) Table -->
        <table>
            <thead>
                <?php
                // helper to render sortable th
                function th_sort($label, $key)
                {
                    $cur = $_GET['sort'] ?? 'updated_at';
                    $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
                    $next = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
                    $arrow = $cur === $key ? ($dir === 'asc' ? '▲' : '▼') : '';
                    $href = 'list.php' . keep(['sort' => $key, 'dir' => $next, 'page' => 1]);
                    echo "<th><a href=\"$href\">$label $arrow</a></th>";
                }
                ?>
                <tr>
                    <?php th_sort('Name', 'name'); ?>
                    <?php th_sort('SKU', 'sku'); ?>
                    <?php th_sort('Price', 'price'); ?>
                    <?php th_sort('Sale Price', 'sale_price'); ?>
                    <?php th_sort('Stock', 'stock'); ?>
                    <?php th_sort('Status', 'status'); ?>
                    <?php th_sort('Updated', 'updated_at'); ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="muted">No products found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                                <span class="muted">/<?= htmlspecialchars($r['slug']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($r['sku'] ?? '') ?></td>
                            <td>₹<?= number_format((float)$r['price'], 2) ?></td>
                            <td><?= is_null($r['sale_price']) ? '—' : '₹' . number_format((float)$r['sale_price'], 2) ?></td>
                            <td><?= (int)$r['stock'] ?></td>
                            <td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td>
                            <td><span class="muted"><?= htmlspecialchars($r['updated_at']) ?></span></td>
                            <td>
                                <a href="edit.php?slug=<?= urldecode($r['slug']) ?>">Edit</a>
                                <!-- You can add a View link for the public page if you want: -->
                                <!-- <span class="muted">·</span> <a href="<?= $BASE_URL ?>product-details.php?slug=<?= urlencode($r['slug']) ?>" target="_blank">View</a> -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 8) Pagination -->
        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php
                $prev = max(1, $page - 1);
                $next = min($pages, $page + 1);
                if ($page > 1) echo '<a href="list.php' . keep(['page' => 1]) . '">« First</a><a href="list.php' . keep(['page' => $prev]) . '">‹ Prev</a>';
                echo '<span class="current">Page ' . $page . ' / ' . $pages . '</span>';
                if ($page < $pages) echo '<a href="list.php' . keep(['page' => $next]) . '">Next ›</a><a href="list.php' . keep(['page' => $pages]) . '">Last »</a>';
                ?>
            </div>
        <?php endif; ?>

        <p class="muted">Found <?= $total ?> result(s).</p>
    </div>
</div>

<script src="<?= $BASE_URL ?>admin/assets/nav.js"></script>

</body>
</html>