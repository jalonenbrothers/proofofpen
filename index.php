<?php
/**
 * index.php — Editor page shell. All capture logic lives in
 * assets/js/capture.js (frontend). Backend contract:
 *   POST /api/create {events, final_text, title?, author?}
 *     -> {ok, pack:{id, chain_head, url, download, verify, warnings}}
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$title = 'ProofOfPen — write with a receipt';
ob_start();
?>
<section class="card" id="finish-card" hidden>
  <h2>&#10003; Proof pack created</h2>
  <p id="pack-summary" class="mono small"></p>
  <p class="small" id="pack-storage"></p>
  <p id="pack-warnings" class="small warn"></p>
  <div class="row">
    <a id="pack-link" class="btn" href="#">Open pack page</a>
    <a id="pack-dl" class="btn" href="#">Download pack (.html)</a>
    <button id="copy-url" class="sec" type="button">Copy verify URL</button>
    <button id="copy-id" class="sec" type="button">Copy pack ID</button>
  </div>
  <details class="small">
    <summary>What this proves — and what it doesn't</summary>
    <p>It proves <strong>integrity</strong>: the pack is a hash chain over how this text
    came to be (keystroke bursts, pauses, pastes), and anyone can recompute it and see
    whether anything was edited after sealing. It does <em>not</em> prove who was at the
    keyboard or how the text was produced — client-side capture is inherently spoofable.
    Receipts, not detection.</p>
  </details>
</section>
<section class="card">
  <div class="row"><label for="title">Title</label><input id="title" maxlength="200" placeholder="Essay title (optional)"></div>
  <div class="row"><label for="author">Author</label><input id="author" maxlength="120" placeholder="Your name (optional)"></div>
  <div class="status-strip" id="status-strip" role="status">
    <span class="dot" id="status-dot"></span>
    <span id="status">recording</span>
    <span aria-hidden="true">·</span>
    <span class="mono small" id="stat-elapsed">0:00</span>
    <span aria-hidden="true">·</span>
    <span class="mono small" id="stat-words">0 words</span>
    <span aria-hidden="true">·</span>
    <span class="mono small" id="stats">0 events</span>
  </div>
  <textarea id="editor" rows="18" placeholder="Start writing. ProofOfPen records how this text came to be — keystrokes, pauses, pastes — and seals it into a tamper-evident proof pack.&#10;&#10;If you step away, that pause becomes part of the receipt. Pasted text is kept separate from typed text — honest by default."></textarea>
  <p class="banner small" id="paused-banner" hidden>capture paused — text added now is recorded as a pause in the receipt when you resume.</p>
  <div class="row actions">
    <span></span>
    <button id="pause-capture" class="sec" type="button">Pause capture</button>
    <button id="discard-session" class="sec" type="button">Discard session</button>
  </div>
  <div class="row" id="discard-row" hidden role="group" aria-label="Confirm discard">
    <span class="small">Discard the text and the receipt so far? Cannot be undone.</span>
    <button id="discard-yes" class="sec warn-border" type="button">Yes, discard</button>
    <button id="discard-no" type="button">Keep writing</button>
  </div>
  <div class="row actions">
    <button id="finish" disabled>Finish &amp; prove</button>
    <span id="finish-note" class="small">writes nothing anywhere until you seal</span>
  </div>
</section>
<script src="/assets/js/capture.js"></script>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';