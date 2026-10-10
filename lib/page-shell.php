<?php
/**
 * lib/page-shell.php — shared HTML head/nav/footer for site pages.
 * Expects: $title, $body (already-escaped HTML). Optional: $extraHead.
 */

if (!defined('POP_ROOT')) { require_once __DIR__ . '/bootstrap.php'; }
$title = $title ?? 'ProofOfPen';
$extraHead = $extraHead ?? '';
$nav = <<<HTML
<nav>
  <a href="/">Write</a>
  <a href="/verify">Verify</a>
  <a href="/about">About</a>
</nav>
HTML;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="/assets/css/site.css">
<script src="/assets/js/sha256.js"></script>
<script src="/assets/js/replay.js"></script>
<?= $extraHead ?>
</head>
<body>
<header class="site-header"><?= $nav ?></header>
<main>
<?= $body ?>
</main>
<footer class="site-footer"><span>ProofOfPen — process receipts, not detection. MVP Lab.</span></footer>
</body>
</html>
