<?php
declare(strict_types=1);

// holborozzak.hu — megtekintés-naplózó JS-beacon.
//
// Használat:  az esemeny.php részletoldalon az app.js POST-tal hívja a lap
//             betöltése után:  POST view-beacon.php   (törzs: e=<esemény id>)
// Cél:        a 'view' interakciót CSAK valódi böngészőből számoljuk (JS-futtatás
//             után), így egy elosztott, IP-forgató flood-script (curl/python, JS
//             nélkül) nem tudja felpumpálni a látogatószámot — akárhány IP-ről jön.
//
// Védelem:    (1) csak POST; (2) azonos-eredetű (Referer host == a mi hostunk),
//             hogy a végpontot ne lehessen egyszerűen kívülről lőni; (3) a szokásos
//             bot-szűrő + admin opt-out + napi IP-dedup a logInteraction()-ben.
// Nincs válasz-tartalom (204), a naplózás hibája sosem akadhat ki.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

header('X-Robots-Tag: noindex, nofollow');

// Csak POST — a keresők/prefetch GET-je ne számoljon.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}

// Azonos-eredetűség: a Referer hostja egyezzen a kérés hostjával. Ez kizárja a
// végpont közvetlen, oldal nélküli lövését (a hamisítás küszöbét emeli).
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$refHost = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
if ($host === '' || $refHost === '' || $refHost !== $host) {
    http_response_code(204); // csendben elutasít — nem naplóz
    exit;
}

$eventId = (int) ($_POST['e'] ?? 0);
if ($eventId <= 0) {
    http_response_code(204);
    exit;
}

try {
    $pdo = db();
    // Csak létező, közzétett eseményre naplózunk (a szemét-id-ket kiszűri).
    $st = $pdo->prepare("SELECT 1 FROM events WHERE id = ? AND status = 'published' LIMIT 1");
    $st->execute([$eventId]);
    if ($st->fetchColumn()) {
        logInteraction($pdo, $eventId, 'view'); // bot-szűr + opt-out + napi IP-dedup
    }
} catch (Throwable $e) {
    error_log('view-beacon.php hiba: ' . $e->getMessage());
}

http_response_code(204);
exit;
