<?php
declare(strict_types=1);

// holborozzak.hu — kimenő kattintás-átirányító + naplózó.
//
// Használat:  go.php?e=<esemény id>&t=website|ticket
// Működés:    naplózza a kattintást (event_interactions), majd 302-vel átirányít
//             az esemény ADATBÁZISBAN tárolt cél URL-jére.
//
// BIZTONSÁG: a cél URL-t SOHA nem a query stringből vesszük (nyílt átirányítás
//            elkerülése), hanem az esemény id + típus alapján a DB-ből olvassuk.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

header('X-Robots-Tag: noindex, nofollow');

// Engedélyezett típusok → [DB oszlop, interakció-típus]
$MAP = [
    'website' => ['col' => 'website_url', 'type' => 'click_website'],
    'ticket'  => ['col' => 'ticket_url',  'type' => 'click_ticket'],
];

$eventId = (int) ($_GET['e'] ?? 0);
$type    = (string) ($_GET['t'] ?? '');
$fallback = 'esemenyek.php';

if ($eventId <= 0 || !isset($MAP[$type])) {
    header('Location: ' . $fallback, true, 302);
    exit;
}

$target = null;
$slug   = null;
$pdo    = null;
try {
    $pdo = db();
    // A {col} egy fix whitelist-ből jön ($MAP), így biztonságos az interpoláció.
    $st = $pdo->prepare(
        "SELECT slug, {$MAP[$type]['col']} AS target
         FROM events WHERE id = ? AND status = 'published' LIMIT 1"
    );
    $st->execute([$eventId]);
    if ($row = $st->fetch()) {
        $slug   = (string) $row['slug'];
        $target = (string) ($row['target'] ?? '');
    }
} catch (Throwable $e) {
    error_log('go.php DB hiba: ' . $e->getMessage());
}

// Nincs érvényes (http/https) cél → vissza a részletoldalra (ha tudjuk), különben listára.
if ($target === null || $target === ''
    || !filter_var($target, FILTER_VALIDATE_URL)
    || !preg_match('#^https?://#i', $target)) {
    $back = $slug !== null ? ('esemeny/' . rawurlencode($slug)) : $fallback;
    header('Location: ' . $back, true, 302);
    exit;
}

// Naplózás — soha ne akadályozza az átirányítást.
if ($pdo instanceof PDO) {
    try {
        logInteraction($pdo, $eventId, $MAP[$type]['type']);
    } catch (Throwable $e) {
        error_log('go.php naplózás hiba: ' . $e->getMessage());
    }
}

header('Location: ' . $target, true, 302);
exit;
