<?php
/**
 * lib/db.php — pack persistence (PDO, prepared statements only).
 */

declare(strict_types=1);

require_once POP_ROOT . '/src/Pack.php';

function pop_store_pack(array $pack): void
{
    $db = pop_db();
    pop_ensure_table($db);
    $stmt = $db->prepare(
        'INSERT INTO packs (id, created_at, author_name, title, head_hash, final_text, event_count, duration_s, pack_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $pack['id'],
        date('Y-m-d H:i:s', strtotime($pack['created_at'])),
        $pack['author'],
        $pack['title'],
        $pack['chain_head'],
        $pack['final_text'],
        count($pack['events']),
        (int)($pack['duration_ms'] / 1000),
        json_encode($pack, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function pop_get_pack(string $id): ?array
{
    $db = pop_db();
    pop_ensure_table($db);
    $stmt = $db->prepare('SELECT pack_json FROM packs WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { return null; }
    $pack = json_decode($row['pack_json'], true);
    return is_array($pack) ? $pack : null;
}

/** Create the table if missing (SQLite dev fallback needs this; MySQL should have run migrations). */
function pop_ensure_table(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($isSqlite) {
        $db->exec('CREATE TABLE IF NOT EXISTS packs (
            id TEXT PRIMARY KEY, created_at TEXT NOT NULL, author_name TEXT NOT NULL DEFAULT \'\',
            title TEXT NOT NULL DEFAULT \'\', head_hash TEXT NOT NULL, final_text TEXT NOT NULL,
            event_count INTEGER NOT NULL DEFAULT 0, duration_s INTEGER NOT NULL DEFAULT 0,
            pack_json TEXT NOT NULL)');
    }
}
