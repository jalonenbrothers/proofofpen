<?php
/**
 * api/verify.php — verify a pack and return the verdict as JSON.
 *
 * POST JSON: {pack:{...}} | {id:"..."}  (id = verify the stored copy)
 * 200 {ok:true, verdict:{valid, reason?, first_bad_index, stats, warnings}}
 */

declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    pop_json(['ok' => false, 'error' => 'POST required'], 405);
}

$input = pop_input();
$pack = null;

if (isset($input['id']) && is_string($input['id']) && preg_match('/^[0-9A-Z]{6,16}$/', $input['id'])) {
    $pack = pop_get_pack($input['id']);
    if ($pack === null) {
        pop_json(['ok' => false, 'error' => 'pack not found'], 404);
    }
} elseif (isset($input['pack']) && is_array($input['pack'])) {
    $pack = $input['pack'];
} else {
    pop_json(['ok' => false, 'error' => 'provide {id} or {pack}'], 422);
}

try {
    $verdict = Pack::verify($pack);
} catch (Throwable $ex) {
    pop_json(['ok' => false, 'error' => 'malformed pack'], 422);
}
$verdict['warnings'] = Pack::audit($pack['events'] ?? []);
$verdict['pack_id'] = $pack['id'] ?? null;

pop_json(['ok' => true, 'verdict' => $verdict, 'pack' => $pack]);
