<?php
declare(strict_types=1);

// EGYSZERI karbantartó végpont: az analitika-táblák ürítése (tiszta méréskezdés).
// Token-védett, POST-only (a Rackhost proxy a custom headert eldobja → POST-törzs).
// A futtatás után ez a fájl AZONNAL törlendő a repóból (és redeploy).

require __DIR__ . '/db.php';

$TOKEN = '5a8da516b737ed284e845f5aebe0efd7ab23658de220ec47e3617571add355d1';
$got = (string) ($_POST['token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $got === '' || !hash_equals($TOKEN, $got)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$tables = [
    'event_interactions',
    'page_views',
    'ai_referrals',
    'search_referrals',
    'event_impressions_daily',
];
foreach ($tables as $t) {
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        $pdo->exec("TRUNCATE TABLE {$t}");
        echo "{$t}: {$n} sor torolve\n";
    } catch (Throwable $e) {
        echo "{$t}: kihagyva — " . $e->getMessage() . "\n";
    }
}
echo "KESZ\n";
