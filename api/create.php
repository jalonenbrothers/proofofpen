<?php
/**
 * api/create.php — POST a captured session, get back a stored proof pack.
 *
 * POST JSON: {events:[{t,type,...}], final_text, title?, author?}
 *   events: see src/Pack.php::build
 * 200 {ok:true, pack:{id, chain_head, warnings, url, ...}}
 * 4xx {ok:false, error}
 */

declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    pop_json(['ok' => false, 'error' => 'POST required'], 405);
}

$input = pop_input();
try {
    [$pack, $warnings] = Pack::build($input);
} catch (InvalidArgumentException $ex) {
    pop_json(['ok' => false, 'error' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    error_log('pop create: ' . $ex->getMessage());
    pop_json(['ok' => false, 'error' => 'server error'], 500);
}

$pack = Pack::withEventHashes($pack);
try {
    pop_store_pack($pack);
} catch (PDOException $ex) {
    // 23000 = duplicate id. Pack ids are 60-bit; a genuine collision is
    // astronomically rare but input can be deterministic (tests). Salt + retry once.
    if ($ex->getCode() === '23000') {
        $pack['id'] = Pack::packId($pack['chain_head'], (int)(substr((string)microtime(true), 0, 10) . '000'));
        try {
            pop_store_pack($pack);
        } catch (PDOException $ex2) {
            error_log('pop store: ' . $ex2->getMessage());
            pop_json(['ok' => false, 'error' => 'storage failed'], 500);
        }
    } else {
        error_log('pop store: ' . $ex->getMessage());
        pop_json(['ok' => false, 'error' => 'storage failed'], 500);
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

pop_json([
    'ok'   => true,
    'pack' => [
        'id'         => $pack['id'],
        'chain_head' => $pack['chain_head'],
        'url'        => $base . '/p/' . $pack['id'],
        'download'   => $base . '/download.php?id=' . $pack['id'],
        'verify'     => $base . '/verify?id=' . $pack['id'],
        'warnings'   => $warnings,
    ],
]);
