<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/helpers.php';

start_app_session();

if (empty($_SESSION['admin'])) {
    redirect('/admin/login.php');
}

$current_admin = $_SESSION['admin'];

function require_admin(): array
{
    global $current_admin;
    return $current_admin;
}
