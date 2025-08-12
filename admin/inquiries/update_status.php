<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../_inc/auth_guard.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){ redirect('list.php'); }
verify_csrf();
$id=(int)input('id');
$status=input('status');
if($id && in_array($status,['new','in_progress','closed'])){
    $stmt=db()->prepare('UPDATE inquiries SET status=? WHERE id=?');
    $stmt->execute([$status,$id]);
    flash_set('success','Status updated');
    redirect('view.php?id='.$id);
}
redirect('list.php');
