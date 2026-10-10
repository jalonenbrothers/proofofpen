<?php
/**
 * p.php — shareable pack view: /p/<id>. Server-side render + inline JSON
 * for the frontend's replay UI. 404 page for unknown ids.
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/db.php';

$id = (string)($_SERVER['PATH_INFO'] ?? '');
// Nginx/rewrite: /p/ABC123 rewrites here with ?id=. Direct: /p.php/ABC123.
if ($id === '' && isset($_GET['id'])) { $id = (string)$_GET['id']; }
$id = strtoupper(ltrim($id, '/'));
if (!preg_match('/^[0-9A-Z]{6,16}$/', $id)) { $id = ''; }

$pack = $id !== '' ? pop_get_pack($id) : null;
if ($pack === null) {
    http_response_code(404);
    $title = 'Pack not found — ProofOfPen';
    $body = '<h1>Pack not found</h1><p>No proof pack with this id. Check the link, or verify a pack on the <a href="/verify">verify page</a>.</p>';
    include __DIR__ . '/lib/page-shell.php';
    exit;
}

$verdict = Pack::verify($pack);
$warnings = Pack::audit($pack['events']);
$packJson = json_encode($pack, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$verdictJson = json_encode($verdict + ['warnings' => $warnings], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$title = 'Pack ' . e($pack['id']) . ' — ProofOfPen';
$body = <<<HTML
<div class="banner {$verdict['valid']}">{$verdict['valid']}</div>
<section class="card">
  <h1>Proof pack {$id}</h1>
  <p class="meta">chain head: <span class="mono">{$verdict['chain_head']}</span></p>
  <p id="verdict-detail" class="meta"></p>
</section>
<section class="card">
  <h2>Replay</h2>
  <div><button id="play">Play</button> <button class="sec" id="speed">Speed 1×</button></div>
  <input type="range" id="scrub" min="0" max="1000" value="0">
  <div id="replay-text" class="essay"></div>
  <p class="small" id="replay-note"></p>
</section>
<section class="card">
  <h2>Final text</h2>
  <div class="essay">{$finalText}</div>
  <p><a href="/download.php?id={$id}">Download self-contained pack (.html)</a></p>
</section>
HTML;
// $body was built before escaping; rebuild final text properly:
ob_start();
?>
<div class="banner <?= $verdict['valid'] ? 'valid' : 'bad' ?>"><?= $verdict['valid'] ? 'VALID' : 'TAMPERED' ?></div>
<section class="card">
  <h1>Proof pack <?= e($pack['id']) ?></h1>
  <p class="meta">chain head: <span class="mono"><?= e($pack['chain_head']) ?></span></p>
  <p class="small">Receipts, not detection: this verdict says the recorded session is intact — it does not claim who or what wrote it.</p>
  <?php if ($warnings): ?><ul><?php foreach ($warnings as $w): ?><li class="warn"><?= e($w) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<section class="card">
  <h2>Replay</h2>
  <div><button id="play">Play</button> <button class="sec" id="speed">Speed 1×</button></div>
  <input type="range" id="scrub" min="0" max="1000" value="0">
  <div id="replay-text" class="essay"></div>
  <p class="small" id="replay-note"></p>
</section>
<section class="card">
  <h2>Final text</h2>
  <div class="essay"><?= e($pack['final_text']) ?></div>
  <p><a href="/download.php?id=<?= e($pack['id']) ?>">Download self-contained pack (.html)</a> · <a href="/verify?id=<?= e($pack['id']) ?>">Verify again</a></p>
</section>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';
