<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/helpers.php';

start_app_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
setcookie('remember', '', time() - 3600, '/');
redirect('/admin/login.php');
