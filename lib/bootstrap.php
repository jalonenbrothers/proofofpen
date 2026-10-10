<?php
/**
 * lib/bootstrap.php — shared init: config, PDO, helpers.
 * Plain PHP, no frameworks. Include from every entry point.
 */

declare(strict_types=1);

define('POP_ROOT', dirname(__DIR__));

/* Load config: lib/config.php (real) or fallback to example + SQLite in dev. */
$configFile = POP_ROOT . '/lib/config.php';
if (is_file($configFile)) {
    $POP_CONFIG = require $configFile;
    $POP_DB_FALLBACK = false;
} else {
    $POP_CONFIG = require POP_ROOT . '/lib/config.example.php';
    $POP_DB_FALLBACK = true;
}

function pop_db(): PDO
{
    global $POP_CONFIG, $POP_DB_FALLBACK, $POP_DB;
    if ($POP_DB instanceof PDO) {
        return $POP_DB;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($POP_DB_FALLBACK) {
        // Dev fallback: SQLite so the app is runnable without MySQL.
        $dir = POP_ROOT . '/data';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $POP_DB = new PDO('sqlite:' . $dir . '/proofofpen.sqlite', null, null, $opts);
        $POP_DB->sqliteCreateFunction('SHA2', fn($s, $bits) => hash('sha256', (string)$s), 2);
    } else {
        $POP_DB = new PDO($POP_CONFIG['db_dsn'], $POP_CONFIG['db_user'], $POP_CONFIG['db_pass'], $opts);
    }
    return $POP_DB;
}

function pop_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function pop_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) {
        pop_json(['error' => 'invalid JSON body'], 400);
    }
    return $data;
}

/** HTML escaping helper — use for ALL dynamic output in pages. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
