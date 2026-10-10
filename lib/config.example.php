<?php
/**
 * lib/config.php — ProofOfPen configuration.
 * COPY THIS FILE TO config.php (same dir) AND FILL IN YOUR Hostinger DB.
 * config.php is git-ignored on purpose: no secrets in the repo.
 *
 * Dev convenience: if POP_DEV=1, missing config falls back to SQLite at
 * data/proofofpen.sqlite so the app runs without MySQL.
 */

return [
    // Hostinger values from hPanel → Databases
    'db_dsn'  => 'mysql:host=localhost;dbname=YOURDB;charset=utf8mb4',
    'db_user' => 'YOURUSER',
    'db_pass' => 'YOURPASS',

    // Set true on production; false while testing
    'debug'   => false,
];
