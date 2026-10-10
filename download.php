<?php
/**
 * download.php — GET ?id=CODE → self-contained pack HTML as attachment.
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/export.php';

$id = strtoupper((string)($_GET['id'] ?? ''));
if (!preg_match('/^[0-9A-Z]{6,16}$/', $id)) {
    http_response_code(400);
    exit('Bad pack id');
}
$pack = pop_get_pack($id);
if ($pack === null) {
    http_response_code(404);
    exit('Pack not found');
}
$verdict = Pack::verify($pack);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="proofofpen-' . $id . '.html"');
echo pop_export_html($pack, $verdict, $site);
