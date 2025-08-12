<?php
declare(strict_types=1);

// Application environment: local or production
if (!defined('APP_ENV')) {
    define('APP_ENV', 'local');
}

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'iswift_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'your_db_user'); // TODO: change to real DB user
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'your_db_password'); // TODO: change to real DB password
}

// Session configuration
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'iswift_admin');
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 7200); // 2 hours
}

// CSRF token key
if (!defined('CSRF_TOKEN_KEY')) {
    define('CSRF_TOKEN_KEY', 'iswift_csrf');
}
