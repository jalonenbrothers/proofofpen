# Local dev — ProofOfPen

Two ways to run locally. Both verified on this machine (Ubuntu, Docker 28,
no PHP installed on the host).

## Option A — Docker stack (Apache + MySQL) — mirrors Hostinger exactly

Uses the production .htaccess (real Apache + mod_rewrite), so pretty URLs
(`/api/create`, `/p/<ID>`) behave exactly like the shared host.

    cd dev
    docker compose up -d --build
    # wait ~15s for first MySQL boot, then apply the migration:
    docker compose exec -T mysql mysql -upop -ppop proofofpen < ../migrations/001_create_packs.sql
    # config: lib/config.php already points at this stack (git-ignored file,
    # contents are in dev/README.md; see below if missing)

Open http://localhost:18080

    # tests (needs no host PHP for e2e; unit tests run in the container):
    docker compose exec web php tests/run.php
    python3 tests/e2e.py http://localhost:18080

    # teardown: docker compose down   (add -v to wipe the DB too)

`lib/config.php` for this stack (git-ignored, create if missing):

    <?php
    return [
        'db_dsn'  => 'mysql:host=mysql;dbname=proofofpen;charset=utf8mb4',
        'db_user' => 'pop',
        'db_pass' => 'pop',
        'debug'   => true,
    ];

## Option B — PHP built-in server, no MySQL (SQLite fallback)

Needs PHP >= 8 on the host. MySQL-less quick run: with no config.php and
`POP_DEV=1`, the app falls back to SQLite under `data/` (auto-created).

    POP_DEV=1 php -S localhost:8000 dev-router.php

Open http://localhost:8000

- dev-router.php mimics the .htaccess rewrite rules for the built-in server.
- data/ is git-ignored; delete it to reset local packs.

## No composer, no npm, no build step — in every mode.
