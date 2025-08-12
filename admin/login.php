<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

start_app_session();

if (!empty($_SESSION['admin'])) {
    redirect('/admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(input('email'));
    $password = input('password');
    $remember = input('remember');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = $email . '|' . $ip;
    $attempts = $_SESSION['login_attempts'][$key] ?? [];
    $attempts = array_filter($attempts, fn($ts) => $ts > time() - 900);
    if (count($attempts) >= 5) {
        $error = 'Too many failed attempts. Please try again later.';
    } else {
        $stmt = db()->prepare('SELECT id,name,email,password_hash FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['login_attempts'][$key] = [];
            $stmt = db()->prepare('UPDATE admins SET last_login_at=NOW() WHERE id=?');
            $stmt->execute([$admin['id']]);
            $_SESSION['admin'] = ['id'=>$admin['id'],'name'=>$admin['name'],'email'=>$admin['email']];
            if ($remember && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                setcookie(session_name(), session_id(), time()+86400*30, '/', '', true, true);
            }
            redirect('/admin/index.php');
        } else {
            $attempts[] = time();
            $_SESSION['login_attempts'][$key] = $attempts;
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
<div style="max-width:400px;margin:80px auto;" class="card">
    <h2>Admin Login</h2>
    <?php if ($error): ?><p class="form-error"><?php echo e($error); ?></p><?php endif; ?>
    <form method="post">
        <?php echo csrf_field(); ?>
        <label>Email
            <input type="email" name="email" value="<?php echo e(input('email')); ?>" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label><input type="checkbox" name="remember" value="1"> Remember me</label>
        <input type="submit" value="Login">
    </form>
</div>
</body>
</html>
