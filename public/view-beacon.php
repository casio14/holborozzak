<?php
declare(strict_types=1);

// holborozzak.hu — látogatottság-naplózó JS-beacon.
//
// Használat:  MINDEN publikus oldalon az app.js POST-tal hívja a lap betöltése után:
//             POST view-beacon.php   (törzs: p=<útvonal>&r=<hivatkozó>[&e=<esemény id>])
// Cél:        az oldalmegnyitást (page_views) és az esemény-megtekintést (event view)
//             CSAK valódi böngészőből számoljuk (JS-futtatás után), így egy elosztott,
//             IP-forgató flood-script (curl/python, JS nélkül) nem tudja felpumpálni a
//             látogató-/megnyitásszámot — akárhány IP-ről jön.
//
// A valódi oldal útvonalát (p) és hivatkozóját (r) a JS küldi, mert a beacon saját
// REQUEST_URI/Referer-je a view-beacon.php lenne.
//
// Védelem:    (1) csak POST; (2) azonos-eredetű (Referer host == a mi hostunk),
//             hogy a végpontot ne lehessen egyszerűen kívülről lőni; (3) a szokásos
//             bot-szűrő + admin opt-out (+ event view-nál napi IP-dedup) a naplózókban.
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

$path = (string) ($_POST['p'] ?? '');
$ref  = (string) ($_POST['r'] ?? '');
$eventId = (int) ($_POST['e'] ?? 0);

try {
    // Oldalmegnyitás (page_views) — minden oldalon; a valós útvonal/hivatkozó a JS-ből.
    logPageView($path !== '' ? $path : null, $ref);

    // Esemény-megtekintés (event view) — csak ha érvényes, közzétett esemény id jött.
    if ($eventId > 0) {
        $pdo = db();
        $st = $pdo->prepare("SELECT 1 FROM events WHERE id = ? AND status = 'published' LIMIT 1");
        $st->execute([$eventId]);
        if ($st->fetchColumn()) {
            logInteraction($pdo, $eventId, 'view'); // bot-szűr + opt-out + napi IP-dedup
        }
    }
} catch (Throwable $e) {
    error_log('view-beacon.php hiba: ' . $e->getMessage());
}

http_response_code(204);
exit;
