<?php
/**
 * tests/gen-fixtures.php — regenerates tests/sha256-fixtures.json:
 * PHP-side sha256 of canonical event JSON bodies (the exact strings the
 * JS side must hash identically for offline verification to work).
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/Pack.php';

$events = [
    ['t' => 1728500000000, 'type' => 'snapshot', 'text' => ''],
    ['t' => 1728500001200, 'type' => 'burst', 'n' => 4, 'd' => 900],
    ['t' => 1728500009800, 'type' => 'pause', 'd' => 6000],
    ['t' => 1728500010400, 'type' => 'paste', 'n' => 40],
    ['t' => 1728500013000, 'type' => 'snapshot', 'text' => 'final-ish'],
    ['t' => 1728600000000, 'type' => 'burst', 'n' => 300, 'd' => 25000],
    ['t' => 1728600100000, 'type' => 'away', 'd' => 45000],
];
$fixtures = [];
$prev = Pack::CHAIN_START;
foreach ($events as $i => $ev) {
    $canon = Pack::canon($ev);
    $prev = hash('sha256', $prev . $canon);
    $fixtures[] = ['name' => "chain-$i", 'input' => ($i === 0 ? Pack::CHAIN_START : $fixtures[$i-1]['php']) . $canon, 'php' => $prev];
}
// extra odd inputs: unicode, slashes, big
$fixtures[] = ['name' => 'unicode', 'input' => Pack::canon(['t' => 1, 'type' => 'snapshot', 'text' => "héllo ✓ ✍ “quote” / slash"]), 'php' => ''];
$fixtures[] = ['name' => 'empty', 'input' => '', 'php' => ''];
$fixtures[] = ['name' => '1MB-a', 'input' => str_repeat('a', 1000000), 'php' => ''];
foreach ($fixtures as &$f) { if ($f['php'] === '') { $f['php'] = hash('sha256', $f['input']); } }
unset($f);

file_put_contents(__DIR__ . '/sha256-fixtures.json', json_encode($fixtures, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo 'wrote ' . count($fixtures) . " fixtures\n";
