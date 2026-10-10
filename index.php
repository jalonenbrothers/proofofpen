<?php
/**
 * index.php — Editor page shell. All capture logic lives in
 * assets/js/capture.js (frontend). Backend contract:
 *   POST /api/create {events, final_text, title?, author?}
 *     -> {ok, pack:{id, url, download, verify, warnings}}
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$title = 'ProofOfPen — write with a receipt';
ob_start();
?>
<section class="card" id="finish-card" hidden>
  <h2>Pack ready</h2>
  <p id="pack-summary" class="mono small"></p>
  <p>
    <a id="pack-link" href="#">Open pack page</a> ·
    <a id="pack-dl" href="#">Download self-contained .html</a>
  </p>
  <p class="small" id="pack-warnings"></p>
</section>
<section class="card">
  <div class="row"><label for="title">Title</label><input id="title" maxlength="200" placeholder="Essay title (optional)"></div>
  <div class="row"><label for="author">Author</label><input id="author" maxlength="120" placeholder="Your name (optional)"></div>
  <div class="status-strip"><span class="dot"></span><span id="status">Capturing…</span></div>
  <textarea id="editor" rows="18" placeholder="Start writing. ProofOfPen records how this text came to be — keystrokes, pauses, pastes — and seals it into a tamper-evident proof pack."></textarea>
  <div class="row actions">
    <button id="finish" disabled>Finish &amp; prove</button>
    <span id="stats" class="small"></span>
  </div>
</section>
<script src="/assets/js/capture.js"></script>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';
