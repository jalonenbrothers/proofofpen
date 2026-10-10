# Migrations — ProofOfPen (operator instructions)

Plain SQL files, applied in numeric order. Run them ONCE, in order, in your
MySQL client (Hostinger hPanel → Databases → phpMyAdmin → SQL tab, or the
mysql CLI). Each file is safe to re-run (IF NOT EXISTS).

## Fresh install

1. Create the database (hPanel does this for you when you create a DB user).
2. Open phpMyAdmin, select your database (e.g. `u123456789_proofofpen`).
3. For each file in this directory in numeric order (currently just
   `001_create_packs.sql`): open the *SQL* tab, paste the whole file,
   press *Go*. Alternatively use Import → choose file.
4. Confirm the `packs` table exists.

## CLI alternative (if you have shell access)

    mysql -u USER -p DATABASE < migrations/001_create_packs.sql

## Per-deploy

Before first deploy only. New migration files will be added with higher
numbers — apply any you haven't run yet, in order.

## Rollback

MVP: drop the `packs` table. There are no destructive migrations yet.
