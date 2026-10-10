<?php
/**
 * verify.php — verify page shell. Tabs (paste JSON / upload .html / pack id)
 * are frontend behaviour in assets/js/verify-ui.js. Backend contract:
 *   POST /api/verify {pack:{...}} or {id:"..."} -> {ok, verdict, pack}
 * A ?id= query pre-selects the id tab.
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$preId = isset($_GET['id']) ? preg_match('/^[0-9A-Z]{6,16}$/i', (string)$_GET['id']) ? strtoupper((string)$_GET['id']) : '' : '';
$title = 'Verify a pack — ProofOfPen';
ob_start();
?>
<section class="card">
  <h1>Verify a proof pack</h1>
  <p class="small">Receipts, not detection. This checks whether a pack is intact — it never guesses who or what wrote the text.</p>
  <div class="tabs">
    <button data-tab="id" class="tab active">Pack ID</button>
    <button data-tab="paste" class="tab">Paste JSON</button>
    <button data-tab="upload" class="tab">Upload pack .html</button>
  </div>
  <div id="tab-id" class="tabpane">
    <input id="pack-id" placeholder="e.g. 07ARZM4KD2PW" value="<?= e($preId) ?>">
    <button id="verify-id">Verify</button>
  </div>
  <div id="tab-paste" class="tabpane" hidden>
    <textarea id="pack-json" rows="10" placeholder='Paste the pack JSON here'></textarea>
    <button id="verify-json">Verify</button>
  </div>
  <div id="tab-upload" class="tabpane" hidden>
    <input type="file" id="pack-file" accept=".html,.json">
    <button id="verify-file-btn">Verify integrity</button>
  </div>
</section>
<section id="result" hidden>
  <div id="result-banner" class="banner"></div>
  <p class="small" id="verdict-local"></p>
  <ul id="verdict-notes" class="small" hidden></ul>
  <section class="card">
    <h2>Replay</h2>
    <div><button id="play">Play</button> <button class="sec" id="speed">Speed 1×</button></div>
    <input type="range" id="scrub" min="0" max="1000" value="0" aria-label="Replay position">
    <div id="replay-text" class="essay"></div>
    <p class="small" id="replay-note"></p>
  </section>
  <section class="card">
    <h2>Bursts</h2>
    <p class="small">Typing bursts with sustained speed above 12 chars/s are flagged as
    unusually fast input. That is a fact about the receipt, not a judgment of how the
    text was produced.</p>
    <div id="burst-list"></div>
  </section>
  <section class="card">
    <h2>Final text</h2>
    <div id="final-text" class="essay"></div>
  </section>
</section>
<p class="banner bad small" id="verify-error" hidden></p>
<script src="/assets/js/verify-ui.js"></script>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';
