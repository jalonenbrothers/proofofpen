# ProofOfPen

Capture a live writing session, seal it into a tamper-evident **proof pack**,
share and verify it — online or offline.

**Receipts, not detection.** A pack shows *how a text came to be*
(keystroke bursts, pauses, pastes, snapshots, all hash-chained). It never
claims who or what wrote it.

## Stack

LAMP: PHP 8+, MySQL via PDO, zero frameworks, zero dependencies.
Vanilla HTML5/JS/CSS, no build step. Repo root = web root.

## Install (Hostinger)

1. Upload repo to the subdomain's web root.
2. `cp lib/config.example.php lib/config.php` — fill in DB creds from hPanel.
3. Apply `migrations/001_create_packs.sql` (see migrations/README.md).
4. Done. No build, no composer, no npm.

Local dev without MySQL works out of the box (SQLite fallback in `data/`).

## Layout

    index.php       editor page (capture in assets/js/capture.js)
    verify.php      verify page (paste / upload / pack id)
    p.php           pack view at /p/<ID> (rewritten via .htaccess)
    download.php    self-contained offline pack .html download
    about.php       what this is and is not
    api/create.php  POST {events, final_text, title?, author?} -> {ok, pack}
    api/verify.php  POST {pack} | {id} -> {ok, verdict, pack}
    src/Pack.php    hash-chain build/verify/audit (the core logic)
    lib/            bootstrap, db (PDO), export template, page shell
    migrations/     plain SQL + operator README
    tests/run.php   unit + integration tests (php tests/run.php)

## Proof pack format

JSON: schema 1, title, author, created_at, duration_ms, events[], final_text,
chain_head, id. Every event hashes to `sha256(prev_hash + canonical_json(event))`
from GENESIS; the last hash is the pack fingerprint. Any post-hoc edit breaks
the chain from that event onward. Packs embed per-event hashes (`h`) so the
browser-side verifier (inlined sha256.js in exports) can recompute everything
offline.

Honesty labels baked in: bursts above 12 chars/s sustained are flagged as
"unusually fast input"; pastes are recorded as pastes, never folded into
typed text.

## Tests

    php tests/run.php

Covers chain build/verify round-trip, tamper detection (event edit, head
edit, event deletion), input validation, pack id shape, and a SQLite-backed
store/fetch round trip.

## External APIs

None. Zero cost.
