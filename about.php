<?php
/**
 * about.php — what ProofOfPen is, what it is NOT (receipts, not detection).
 */

declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$title = 'About — ProofOfPen';
ob_start();
?>
<section class="card">
  <h1>About ProofOfPen</h1>
  <p>ProofOfPen captures a live writing session — keystroke bursts, pauses,
  pastes, periodic snapshots — and seals it into a tamper-evident
  <strong>proof pack</strong>: every event is hashed into a chain, so any edit
  after the fact breaks the chain and is visible to anyone.</p>
  <h2>Receipts, not detection</h2>
  <p>A proof pack is a <em>process receipt</em>. It shows how a text came to
  be. It does not, and cannot, prove who or what wrote it — a determined
  user can forge a capture. Unusually fast input bursts are flagged honestly
  inside the pack. We never claim courtroom-grade proof.</p>
  <h2>Verify anywhere</h2>
  <p>Packs verify on this site, or offline: the downloaded .html file
  re-checks its own chain in your browser, no server needed.</p>
</section>
<?php
$body = ob_get_clean();
include __DIR__ . '/lib/page-shell.php';
