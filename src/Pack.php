<?php
/**
 * src/Pack.php — the heart of ProofOfPen.
 *
 * A proof pack is a JSON document of session events where every event
 * carries sha256(prev_hash + canonical_json(event_body)). The chain head
 * is the pack fingerprint. Any manual edit breaks every later hash, so
 * verification pinpoints the first tampered event.
 *
 * IMPORTANT (scope risk-1): this is a "process receipt", not a
 * cryptographic guarantee — a determined client can forge a capture.
 * We surface typing-speed anomalies honestly and never claim more.
 */

declare(strict_types=1);

final class Pack
{
    public const SCHEMA       = 1;
    public const CHAIN_START = 'GENESIS';
    /** Sustained typing above this many chars/sec is flagged as unusual. */
    public const FAST_CHARS_PER_SEC = 12.0;

    /**
     * Build a pack array from a client session. $input keys:
     *   events: array of {t: int ms-epoch, type: 'burst'|'snapshot'|'paste'|'pause'|'away', ...}
     *   final_text: string
     * Optional: title, author.
     * Events are validated: order, type whitelist, payload sizes.
     * Returns [pack, warnings] — pack is the canonical, hash-chained doc.
     */
    public static function build(array $input): array
    {
        $eventsRaw = $input['events'] ?? [];
        $finalText = (string)($input['final_text'] ?? '');
        $title     = mb_substr(trim((string)($input['title'] ?? '')), 0, 200);
        $author    = mb_substr(trim((string)($input['author'] ?? '')), 0, 120);

        if (!is_array($eventsRaw) || $eventsRaw === []) {
            throw new InvalidArgumentException('no events captured');
        }
        if (count($eventsRaw) > 5000) {
            throw new InvalidArgumentException('too many events (max 5000)');
        }
        if ($finalText === '') {
            throw new InvalidArgumentException('empty text');
        }
        if (strlen($finalText) > 500000) {
            throw new InvalidArgumentException('text too long (max 500KB)');
        }

        // Validate + normalize events, require non-decreasing timestamps.
        $events = [];
        $lastT = 0;
        foreach ($eventsRaw as $ev) {
            if (!is_array($ev) || !isset($ev['t'], $ev['type'])) {
                throw new InvalidArgumentException('malformed event');
            }
            $t = (int)$ev['t'];
            if ($t < 1000000000000) { // sanity: ms-epoch after 2001-09
                throw new InvalidArgumentException('event timestamp not in ms');
            }
            if ($t < $lastT) {
                throw new InvalidArgumentException('events out of order');
            }
            $lastT = $t;
            $events[] = self::normalizeEvent($ev);
        }

        $body = [
            'schema'     => self::SCHEMA,
            'title'      => $title,
            'author'     => $author,
            'created_at' => date('c'),
            'duration_ms' => $events[count($events) - 1]['t'] - $events[0]['t'],
            'events'     => $events,
            'final_text' => $finalText,
        ];

        $warnings = self::audit($events);

        $chain = self::chainEvents($events);
        // Seal the final text into the head: editing the text without
        // touching events must also break verification.
        $body['chain_head'] = hash('sha256', $chain['head'] . self::canon(['final_text' => $finalText]));

        $body['id'] = self::packId($chain['head'], $events[0]['t']);

        return [$body, $warnings];
    }

    /** Acceptable event shapes; everything else is trimmed away. */
    private static function normalizeEvent(array $ev): array
    {
        $type = (string)$ev['type'];
        $out = ['t' => (int)$ev['t'], 'type' => $type];
        switch ($type) {
            case 'burst':
                // A burst of typed chars: length + duration of the burst.
                $out['n'] = max(1, (int)($ev['n'] ?? 1));
                $out['d'] = max(0, (int)($ev['d'] ?? 0)); // ms
                break;
            case 'paste':
                $out['n'] = max(1, (int)($ev['n'] ?? 1));
                break;
            case 'snapshot':
                // Periodic full-text snapshot (first + last + every ~1KB chunk boundary).
                $text = (string)($ev['text'] ?? '');
                $out['text'] = $text;
                break;
            case 'pause': // >2s of inactivity
            case 'away':  // tab hidden / window blurred
                $out['d'] = max(0, (int)($ev['d'] ?? 0));
                break;
            default:
                throw new InvalidArgumentException('unknown event type: ' . $type);
        }
        return $out;
    }

    /**
     * Hash-chain events: h_i = sha256(h_{i-1} || canonical_json(event_i)).
     * Event json is the EXACT event object the pack stores — verifier
     * re-serializes canonically and compares.
     */
    public static function chainEvents(array $events): array
    {
        $prev = self::CHAIN_START;
        $hashes = [];
        foreach ($events as $ev) {
            $prev = hash('sha256', $prev . self::canon($ev));
            $hashes[] = $prev;
        }
        return ['hashes' => $hashes, 'head' => $prev];
    }

    /** Canonical JSON: sorted keys, unescaped slashes/unicode, no spaces. */
    public static function canon(array $a): string
    {
        self::ksortRecursive($a);
        return json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function ksortRecursive(array &$a): void
    {
        ksort($a);
        foreach ($a as &$v) {
            if (is_array($v)) { self::ksortRecursive($v); }
        }
    }

    /** Pack IDs: 12 chars, unambiguous base32 (Crockford, no I/L/O/U), derived from the head hash. Pure PHP (no gmp). */
    public static function packId(string $headHash, int $t): string
    {
        // 12 base32 chars = 60 bits: 8 bytes of head hash (64 bits, truncated) + time salt.
        $bin = hex2bin(substr($headHash, 0, 16)) . pack('J', $t); // 16 bytes total
        $alpha = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $out = '';
        $bits = 0; $val = 0;
        for ($i = 0; $i < strlen($bin) && strlen($out) < 12; $i++) {
            $val = ($val << 8) | ord($bin[$i]);
            $bits += 8;
            while ($bits >= 5 && strlen($out) < 12) {
                $out .= $alpha[($val >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        while (strlen($out) < 12) { $out .= $alpha[$val & 31]; $val >>= 5; }
        return $out;
    }

    /** Human-readable warnings for suspicious patterns. Integrity-only, receipts not detection. */
    public static function audit(array $events): array
    {
        $warnings = [];
        $fast = 0;
        foreach ($events as $ev) {
            if ($ev['type'] === 'burst' && $ev['d'] > 0) {
                $cps = $ev['n'] / ($ev['d'] / 1000);
                if ($cps > self::FAST_CHARS_PER_SEC) { $fast++; }
            }
        }
        if ($fast > 0) {
            $warnings[] = "$fast unusually fast input burst(s) (>12 chars/s sustained) — shown in the pack for the verifier to see";
        }
        $pastes = count(array_filter($events, fn($e) => $e['type'] === 'paste'));
        if ($pastes > 0) {
            $warnings[] = "$pastes paste event(s) recorded — pasted text is kept separate from typed text in the replay";
        }
        return $warnings;
    }

    /**
     * Verify a pack (as received — from upload, paste, DB or export).
     * Returns a verdict array:
     *   valid: bool
     *   first_bad_index: int|null  (event index of first chain mismatch)
     *   chain_head: recomputed head
     *   stats + warnings for the UI (burst count, pasted chars, fast bursts, duration)
     */
    public static function verify(array $pack): array
    {
        if (($pack['schema'] ?? null) !== self::SCHEMA) {
            return ['valid' => false, 'reason' => 'unsupported schema', 'first_bad_index' => null];
        }
        foreach (['events', 'final_text', 'chain_head'] as $k) {
            if (!isset($pack[$k]) || ($k === 'events' && !is_array($pack['events']))) {
                return ['valid' => false, 'reason' => "missing field: $k", 'first_bad_index' => null];
            }
        }

        $prev = self::CHAIN_START;
        $firstBad = null;
        foreach ($pack['events'] as $i => $ev) {
            // Strip the per-event hash if present before canonical hashing.
            $evBody = $ev;
            unset($evBody['h']);
            $h = hash('sha256', $prev . self::canon($evBody));
            if (isset($ev['h']) && $ev['h'] !== $h) {
                // Claimed hash present but wrong.
                if ($firstBad === null) { $firstBad = $i; }
            }
            $prev = $h;
        }
        // Seal the final text in (same formula as build()).
        $head = hash('sha256', $prev . self::canon(['final_text' => (string)($pack['final_text'] ?? '')]));

        $valid = ($head === $pack['chain_head']) && $firstBad === null;
        if (!$valid && $firstBad === null) {
            // Head mismatch with all claimed per-event hashes intact means
            // the head itself was edited, or events after the last claimed
            // hash were changed. Report head mismatch.
            return ['valid' => false, 'reason' => 'chain head mismatch', 'first_bad_index' => null,
                     'chain_head' => $head] + self::stats($pack['events']);
        }
        if ($firstBad !== null) {
            return ['valid' => false, 'reason' => "event $firstBad fails its hash", 'first_bad_index' => $firstBad,
                     'chain_head' => $head] + self::stats($pack['events']);
        }
        return ['valid' => true, 'reason' => null, 'first_bad_index' => null,
                 'chain_head' => $head] + self::stats($pack['events']);
    }

    /** Descriptive stats for verify/pack UIs. */
    public static function stats(array $events): array
    {
        $bursts = $pastes = $fast = $pause = 0;
        $typedChars = $pastedChars = 0;
        foreach ($events as $ev) {
            switch ($ev['type'] ?? '') {
                case 'burst':
                    $bursts++; $typedChars += (int)($ev['n'] ?? 0);
                    if (($ev['d'] ?? 0) > 0 && $ev['n'] / ($ev['d'] / 1000) > self::FAST_CHARS_PER_SEC) $fast++;
                    break;
                case 'paste':
                    $pastes++; $pastedChars += (int)($ev['n'] ?? 0);
                    break;
                case 'pause': $pause++; break;
            }
        }
        return ['stats' => [
            'bursts' => $bursts, 'typed_chars' => $typedChars,
            'pastes' => $pastes, 'pasted_chars' => $pastedChars,
            'fast_bursts' => $fast, 'pauses' => $pause,
            'events' => count($events),
        ]];
    }

    /**
     * Recompute per-event hashes and attach them (key 'h') so the exported
     * pack is self-describing: verifier (and replay) can show the chain.
     */
    public static function withEventHashes(array $pack): array
    {
        $prev = self::CHAIN_START;
        $out = $pack;
        $out['events'] = [];
        foreach ($pack['events'] as $ev) {
            $h = hash('sha256', $prev . self::canon($ev));
            $ev['h'] = $h;
            $out['events'][] = $ev;
            $prev = $h;
        }
        return $out;
    }
}
