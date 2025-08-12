<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

/** Escape HTML entities */
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate or retrieve CSRF token */
function csrf_token(): string
{
    start_app_session();
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/** Return hidden CSRF field */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Verify CSRF token on POST requests */
function verify_csrf(): void
{
    start_app_session();
    $token = $_POST['_token'] ?? '';
    if (!$token || !hash_equals($_SESSION[CSRF_TOKEN_KEY] ?? '', $token)) {
        http_response_code(419);
        echo 'Page expired. Please go back and try again.';
        exit;
    }
}

/** Redirect helper */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Flash message helpers */
function flash_set(string $type, string $msg): void
{
    start_app_session();
    $_SESSION['flash'][$type][] = $msg;
}

function flash_get(string $type): ?string
{
    start_app_session();
    if (empty($_SESSION['flash'][$type])) {
        return null;
    }
    $msgs = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return implode('<br>', array_map('e', $msgs));
}

/** Safe input helpers */
function input(string $key, $default = '')
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function get(string $key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

/** Slugify utility */
function slugify(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'n-a';
}
