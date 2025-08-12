<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('list.php');
}

verify_csrf();
$id = (int)input('id');
if ($id) {
    $stmt = db()->prepare('DELETE FROM products WHERE id=?');
    $stmt->execute([$id]);
    flash_set('success','Product deleted');
}
redirect('list.php');
