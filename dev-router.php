<?php
/**
 * dev-router.php — router for `php -S localhost:8000 dev-router.php` in dev.
 * Mimics the production .htaccess rules (pretty URLs /api/create, /p/<id>)
 * so the frontend and tests/e2e.py behave identically in dev and prod.
 * NOT used on Hostinger (Apache + .htaccess handles this).
 */

declare(strict_types=1);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = __DIR__;

// Serve real files (assets, download.php, etc.) directly. NOTE: a request
// like /p.php/<ID> (PATH_INFO) is NOT a real file — fall through to the
// p.php rule below so both /p/<ID> and /p.php/<ID> work like Apache.
$file = $root . $uri;
if ($uri !== '/' && is_file($file)) {
    return false; // let the built-in server serve it
}

// PATH_INFO style: /p.php/<ID> -> /p.php?id=<ID>
if (preg_match('#^/p\.php/([0-9A-Za-z]{6,16})$#', $uri, $m)) {
    $_SERVER['SCRIPT_NAME'] = '/p.php';
    $_SERVER['PATH_INFO'] = '/' . $m[1];
    $_GET['id'] = strtoupper($m[1]);
    require $root . '/p.php';
    return true;
}

// /api/create -> /api/create.php, /api/verify -> /api/verify.php
if (preg_match('#^/api/([a-z]+)$#', $uri, $m) && is_file($root . '/api/' . $m[1] . '.php')) {
    $_SERVER['SCRIPT_NAME'] = $uri . '.php';
    require $root . '/api/' . $m[1] . '.php';
    return true;
}

// /p/<ID> -> /p.php?id=<ID>
if (preg_match('#^/p/([0-9A-Za-z]{6,16})$#', $uri, $m)) {
    $_SERVER['SCRIPT_NAME'] = '/p.php';
    $_SERVER['PATH_INFO'] = '/' . $m[1];
    $_GET['id'] = strtoupper($m[1]);
    require $root . '/p.php';
    return true;
}

// Everything else: page shells. /about, /verify, /404 fall back to their .php.
foreach (['/about', '/verify', '/404'] as $page) {
    if ($uri === $page) {
        $_SERVER['SCRIPT_NAME'] = $page . '.php';
        require $root . $page . '.php';
        return true;
    }
}
if ($uri === '/' || $uri === '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require $root . '/index.php';
    return true;
}

// Unknown -> 404 page.
http_response_code(404);
$_SERVER['SCRIPT_NAME'] = '/404.php';
require $root . '/404.php';
return true;