-- 001_create_packs.sql — ProofOfPen base schema
-- Idempotent-ish: CREATE TABLE IF NOT EXISTS. Run after configuring lib/config.php.

CREATE TABLE IF NOT EXISTS packs (
    id           VARCHAR(16)  NOT NULL PRIMARY KEY,
    created_at   DATETIME     NOT NULL,
    author_name  VARCHAR(120) NOT NULL DEFAULT '',
    title        VARCHAR(200) NOT NULL DEFAULT '',
    head_hash    CHAR(64)     NOT NULL,
    final_text   MEDIUMTEXT   NOT NULL,
    event_count  INT UNSIGNED NOT NULL DEFAULT 0,
    duration_s   INT UNSIGNED NOT NULL DEFAULT 0,
    pack_json    LONGTEXT     NOT NULL,
    INDEX idx_packs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kept for reference only: individual events are embedded in packs.pack_json
-- (the hash chain covers them), so no separate events table is needed for MVP.
