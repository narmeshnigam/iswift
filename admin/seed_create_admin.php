<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

// Seeder inputs (TODO: change for production)
$email = 'admin@iswift.in';
$name  = 'Super Admin';
$pass  = 'Admin@123';

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "Admin already exists: {$email}\n";
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $name,
        $email,
        password_hash($pass, PASSWORD_DEFAULT),
        'active'
    ]);

    echo "Admin created: {$email}\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
