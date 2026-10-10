<?php
/**
 * tests/run.php — tiny test runner (no framework, exit code = failures).
 * Usage: php tests/run.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Pack.php';

$PASS = 0; $FAIL = 0; $FAILURES = [];

function check(bool $cond, string $name): void
{
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "ok  $name\n"; }
    else { $FAIL++; $FAILURES[] = $name; echo "FAIL $name\n"; }
}

/* --- fixture: a realistic session --- */
$base = 1728500000000;
$events = [
    ['t' => $base,        'type' => 'snapshot', 'text' => ''],
    ['t' => $base + 1200, 'type' => 'burst', 'n' => 4, 'd' => 900],
    ['t' => $base + 2600, 'type' => 'burst', 'n' => 5, 'd' => 1100],
    ['t' => $base + 9800, 'type' => 'pause', 'd' => 6000],
    ['t' => $base + 10400, 'type' => 'paste', 'n' => 40],
    ['t' => $base + 12000, 'type' => 'burst', 'n' => 30, 'd' => 4000],
    ['t' => $base + 13000, 'type' => 'snapshot', 'text' => 'final-ish'],
];

/* --- build --- */
[$pack, $warnings] = Pack::build([
    'events' => $events, 'final_text' => 'Hello ProofOfPen world.',
    'title' => 'Test essay', 'author' => 'Tester',
]);
check(($pack['schema'] ?? 0) === 1, 'build: schema 1');
check(strlen($pack['chain_head']) === 64 && ctype_xdigit($pack['chain_head']), 'build: 64-hex chain head');
check(strlen($pack['id']) === 12 && preg_match('/^[0-9A-Z]+$/', $pack['id']) === 1, 'build: 12-char base32 id');
check($pack['duration_ms'] === 13000, 'build: duration computed');
check(!in_array('t', array_diff(array_keys($pack), ['t']), true) && !isset($pack['events'][0]['h']), 'build: events not yet hashed');
check(count(array_filter($warnings, fn($w) => str_contains($w, 'fast'))) === 0, 'build: fixture produces no fast-burst warning');
check(count(array_filter($warnings, fn($w) => str_contains($w, 'paste'))) === 1, 'build: fixture paste recorded in warnings');

/* fast burst detection */
[, $w2] = Pack::build([
    'events' => [['t' => $base, 'type' => 'burst', 'n' => 50, 'd' => 500]],
    'final_text' => 'x',
]);
check(count($w2) === 1 && str_contains($w2[0], 'fast'), 'audit: fast burst (>12 cps) flagged');

/* paste warning */
[, $w3] = Pack::build([
    'events' => [['t' => $base, 'type' => 'burst', 'n' => 3, 'd' => 800], ['t' => $base + 1000, 'type' => 'paste', 'n' => 99]],
    'final_text' => 'y',
]);
check(count(array_filter($w3, fn($w) => str_contains($w, 'paste'))) === 1, 'audit: paste recorded');

/* --- verify round-trip --- */
$hashed = Pack::withEventHashes($pack);
$v = Pack::verify($hashed);
check($v['valid'] === true, 'verify: untampered pack is VALID');
check($v['chain_head'] === $pack['chain_head'], 'verify: recomputed head matches');
check($v['stats']['events'] === 7 && $v['stats']['pastes'] === 1 && $v['stats']['typed_chars'] === 39, 'verify: stats computed');

/* --- tamper: edit one event payload --- */
$t1 = $hashed;
$t1['events'][2]['n'] = 6; // was 5
$v1 = Pack::verify($t1);
check($v1['valid'] === false, 'tamper: edited event detected');
check($v1['first_bad_index'] === 2, 'tamper: first mismatch points at event 2');

/* --- tamper: edit final_text only --- */
$t2 = $hashed;
$t2['final_text'] = 'Rewritten text.';
$v2 = Pack::verify($t2);
check($v2['valid'] === false && str_contains($v2['reason'], 'head'), 'tamper: final_text edit breaks head');

/* --- tamper: delete an event --- */
$t3 = $hashed;
array_splice($t3['events'], 1, 1);
$v3 = Pack::verify($t3);
check($v3['valid'] === false, 'tamper: deleted event detected');

/* --- tamper: append an event --- */
$t4 = $hashed;
$t4['events'][] = ['t' => $base + 20000, 'type' => 'burst', 'n' => 1, 'd' => 10];
$v4 = Pack::verify($t4);
check($v4['valid'] === false, 'tamper: appended event breaks head');

/* --- canonical json stability --- */
$canon = Pack::canon(['b' => 2, 'a' => 1, 'n' => ['z' => 1, 'y' => ['k' => 1, 'j' => 2]]]);
check($canon === '{"a":1,"b":2,"n":{"y":{"j":2,"k":1},"z":1}}', 'canon: sorted-key stable json');

/* --- input validation --- */
foreach ([
    ['events' => [], 'final_text' => 'x'],                                   // no events
    ['events' => $events, 'final_text' => ''],                               // empty text
    ['events' => [['t' => 1000, 'type' => 'burst', 'n' => 2]], 'final_text' => 'x'], // t not ms
    ['events' => array_merge($events, [$events[0]]), 'final_text' => 'x'],   // out of order
    ['events' => [['t' => $base, 'type' => 'warp', 'n' => 1]], 'final_text' => 'x'], // bad type
    ['final_text' => 'x'],                                                   // missing events
] as $i => $bad) {
    $threw = false;
    try { Pack::build($bad); } catch (InvalidArgumentException) { $threw = true; }
    check($threw, "validation: bad input #$i rejected");
}

/* --- big-but-ok session (500 events) --- */
$big = [];
for ($i = 0; $i < 500; $i++) {
    $big[] = ['t' => $base + $i * 2000, 'type' => 'burst', 'n' => 3, 'd' => 700];
}
[$bp, ] = Pack::build(['events' => $big, 'final_text' => str_repeat('a', 1500)]);
$bh = Pack::withEventHashes($bp);
check(Pack::verify($bh)['valid'] === true, 'scale: 500-event pack verifies');

/* --- pack id unambiguity --- */
check(!str_contains(Pack::packId('deadbeef' . str_repeat('0', 56), $base), 'l'), 'id: base32 alphabet (no I/L/O/U)');

/* --- chain determinism across key order in input events --- */
$shuffledKeys = array_map(fn($ev) => array_reverse($ev, true), $events);
[$p2, ] = Pack::build(['events' => $shuffledKeys, 'final_text' => 'Hello ProofOfPen world.', 'title' => 'Test essay', 'author' => 'Tester']);
check(Pack::withEventHashes($p2)['chain_head'] === $hashed['chain_head'], 'canon: event key order does not change chain');

/* --- summary --- */
echo "\n" . ($FAIL === 0 ? "ALL PASSED" : "FAILURES: " . implode('; ', $FAILURES)) . " ($PASS passed, $FAIL failed)\n";
exit($FAIL === 0 ? 0 : 1);
