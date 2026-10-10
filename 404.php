<?php
// 404.php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
http_response_code(404);
$title = 'Not found — ProofOfPen';
$body = '<section class="card"><h1>404 — nothing here</h1><p><a href="/">Write a proof pack</a> or <a href="/verify">verify one</a>.</p></section>';
include __DIR__ . '/lib/page-shell.php';
