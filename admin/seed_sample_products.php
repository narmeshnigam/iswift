<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$products = [
    [
        'title' => 'iSwift Smart Lock',
        'slug' => 'iswift-smart-lock',
        'sku' => 'SL-001',
        'short_desc' => 'Sample smart lock.',
        'description' => 'Sample description for smart lock.',
        'price' => 5000.00,
        'sale_price' => 4500.00,
        'is_featured' => 1,
        'category_slug' => 'smart-locks',
        'images' => [
            ['url' => '/uploads/products/sample1.jpg', 'alt_text' => 'Smart Lock - Front'],
            ['url' => '/uploads/products/sample1b.jpg', 'alt_text' => 'Smart Lock - Side'],
        ],
    ],
    [
        'title' => 'iSwift Video Door Phone',
        'slug' => 'iswift-video-door-phone',
        'sku' => 'VDP-001',
        'short_desc' => 'Sample video door phone.',
        'description' => 'Sample description for video door phone.',
        'price' => 8000.00,
        'sale_price' => 7500.00,
        'is_featured' => 1,
        'category_slug' => 'video-door-phones',
        'images' => [
            ['url' => '/uploads/products/sample2.jpg', 'alt_text' => 'Video Door Phone - Front'],
            ['url' => '/uploads/products/sample2b.jpg', 'alt_text' => 'Video Door Phone - Screen'],
        ],
    ],
];

try {
    $pdo = db();
    $pdo->beginTransaction();

    foreach ($products as $p) {
        $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
        $stmt->execute([$p['slug']]);
        if ($stmt->fetch()) {
            continue; // product already exists
        }

        $stmtCat = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
        $stmtCat->execute([$p['category_slug']]);
        $cat = $stmtCat->fetch();
        $categoryId = $cat['id'] ?? null;

        $stmt = $pdo->prepare('INSERT INTO products (title, slug, sku, short_desc, description, price, sale_price, is_featured, is_active, category_id, meta_title, meta_desc, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $p['title'],
            $p['slug'],
            $p['sku'],
            $p['short_desc'],
            $p['description'],
            $p['price'],
            $p['sale_price'],
            $p['is_featured'],
            $categoryId,
            $p['title'],
            $p['short_desc'],
        ]);
        $productId = (int)$pdo->lastInsertId();

        $stmtImg = $pdo->prepare('INSERT INTO product_images (product_id, url, alt_text, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())');
        $sort = 0;
        foreach ($p['images'] as $img) {
            $stmtImg->execute([$productId, $img['url'], $img['alt_text'], $sort++]);
        }
    }

    $pdo->commit();
    echo "Sample products seeding completed.\n";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
