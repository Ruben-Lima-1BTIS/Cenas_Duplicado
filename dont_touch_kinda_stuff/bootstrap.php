<?php

function internhub_is_https_request() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

function internhub_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = internhub_is_https_request();
    $isProduction = strtolower((string) getenv('APP_ENV')) === 'production';
    $secureCookies = $isHttps;

    if ($isProduction && !$isHttps) {
        http_response_code(403);
        exit('HTTPS is required in production.');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookies,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Strict', '', $secureCookies, true);
    }

    session_start();

    if (empty($_SESSION['__internhub_session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['__internhub_session_initialized'] = time();
    }
}

function internhub_require_db() {
    $paths = [
        __DIR__ . '/db.php',
        dirname(__DIR__) . '/db.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }

    die('Database connection file not found.');
}

function internhub_bootstrap($options = []) {
    internhub_start_session();

    if (!empty($options['db'])) {
        internhub_require_db();
    }

    if (!empty($options['csrf'])) {
        require_once __DIR__ . '/CSRFToken.php';
    }
}

function internhub_destroy_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
