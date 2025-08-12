<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

$admin = require_admin();
$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $current = input('current_password');
    $new = input('new_password');
    $confirm = input('confirm_password');
    if($new!==$confirm) $errors['confirm']='Passwords do not match';
    if(strlen($new)<8 || !preg_match('/[A-Za-z]/',$new) || !preg_match('/\d/',$new)) $errors['new']='Password must be 8 chars with letters and digits';
    // verify current
    $stmt=db()->prepare('SELECT password_hash FROM admins WHERE id=?');
    $stmt->execute([$admin['id']]);
    $hash=$stmt->fetchColumn();
    if(!$hash || !password_verify($current,$hash)) $errors['current']='Current password incorrect';
    if(!$errors){
        $newHash=password_hash($new,PASSWORD_DEFAULT);
        db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([$newHash,$admin['id']]);
        flash_set('success','Password updated');
    }
}

include __DIR__ . '/../_inc/header.php';
?>
<h2>Change Password</h2>
<?php if($msg=flash_get('success')): ?><p class="card"><?php echo $msg; ?></p><?php endif; ?>
<form method="post" class="card">
<?php echo csrf_field(); ?>
<label>Current Password
<input type="password" name="current_password" required>
<?php if(!empty($errors['current'])) echo '<div class="form-error">'.e($errors['current']).'</div>'; ?>
</label>
<label>New Password
<input type="password" name="new_password" required>
<?php if(!empty($errors['new'])) echo '<div class="form-error">'.e($errors['new']).'</div>'; ?>
</label>
<label>Confirm Password
<input type="password" name="confirm_password" required>
<?php if(!empty($errors['confirm'])) echo '<div class="form-error">'.e($errors['confirm']).'</div>'; ?>
</label>
<input type="submit" value="Change">
</form>
<?php include __DIR__ . '/../_inc/footer.php';
