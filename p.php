<?php
/**
 * p.php — shareable pack view: /p/<id>. Server-side render + embedded pack JSON
 * for the replay UI (page-shell already loads sha256.js + replay.js).
 * 404 page for unknown ids.
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
$stats = $verdict['stats'] ?? [];

// Embed the pack once, hex-escaped inside a JSON script block (cannot break out).
$packJson = json_encode($pack, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$packJsonHex = bin2hex($packJson); // decode client-side: hex is markup-proof
$extraHead = '<script id="packHex">' . $packJsonHex . '</script>';

$title = 'Pack ' . e($pack['id']) . ' — ProofOfPen';
ob_start();
?>
<div class="banner <?= $verdict['valid'] ? 'valid' : 'bad' ?>"><?= $verdict['valid'] ? '&#10003; VALID' : '&#10007; TAMPERED' ?></div>
<section class="card">
  <h1>Proof pack <?= e($pack['id']) ?></h1>
  <p class="meta">chain head: <span class="mono" title="<?= e($pack['chain_head']) ?>"><?= e(mb_substr((string)$pack['chain_head'], 0, 16)) ?>…</span></p>
  <p class="meta small">
    <?= (int)($stats['events'] ?? 0) ?> events ·
    <?= (int)($stats['typed_chars'] ?? 0) ?> chars typed ·
    <?= (int)($stats['pasted_chars'] ?? 0) ?> chars pasted ·
    <?= (int)($stats['pauses'] ?? 0) ?> pauses
  </p>
  <p class="small">Receipts, not detection: this verdict says the recorded session is intact — it does not claim who or what wrote it.</p>
  <?php if ($warnings): ?><ul class="small"><?php foreach ($warnings as $w): ?><li class="warn"><?= e($w) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<section class="card">
  <h2>Replay</h2>
  <div><button id="play">Play</button> <button class="sec" id="speed">Speed 1×</button></div>
  <input type="range" id="scrub" min="0" max="1000" value="0" aria-label="Replay position">
  <div id="replay-text" class="essay"></div>
  <p class="small" id="replay-note"></p>
</section>
<section class="card">
  <h2>Final text</h2>
  <div class="essay"><?= e($pack['final_text']) ?></div>
  <p><a href="/download.php?id=<?= e($pack['id']) ?>">Download self-contained pack (.html)</a> · <a href="/verify?id=<?= e($pack['id']) ?>">Verify again</a></p>
  <p class="small">Pack stored on this server for 90 days. The downloaded .html file works offline, forever.</p>
</section>
<script>
(function () {
  'use strict';
  var hex = document.getElementById('packHex').textContent.trim();
  var json = '';
  for (var i = 0; i < hex.length; i += 2) json += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
  var pack = JSON.parse(decodeURIComponent(escape(json)));
  popReplay(pack, {
    text: document.getElementById('replay-text'),
    play: document.getElementById('play'),
    speed: document.getElementById('speed'),
    scrub: document.getElementById('scrub'),
    note: document.getElementById('replay-note')
  });
})();
</script>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';