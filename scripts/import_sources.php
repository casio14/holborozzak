<?php
declare(strict_types=1);

/**
 * Célzott forrás-importáló (GitHub Actions, heti).
 *
 * A napi `collect_events.php` NYÍLT web_search-t futtat; ez a script ezzel szemben
 * egy KURÁLT LISTÁnyi konkrét programoldalt (borvacsora- és kóstoló-helyszínek,
 * bornaptárak) tölt le, és MINDEGYIKBŐL több közelgő eseményt nyer ki a Claude-dal.
 * Az eredményt ugyanarra a token-védett `collect-ingest.php`-ra POST-olja, ahol a
 * dedup + beszúrás történik (`event_candidates`, `new`). A jóváhagyás az adminban kézi.
 *
 * Miért kell külön a web_search mellé? A kis borbár-/étterem-kóstolók dátumai gyakran
 * csak a saját programoldalukon vannak fent, amit a nyílt keresés nem mindig hoz be.
 *
 * Env: ANTHROPIC_API_KEY, ANTHROPIC_MODEL (opc.), COLLECT_URL, COLLECT_TOKEN.
 */

function envv(string $k, string $def = ''): string
{
    $v = getenv($k);
    return $v === false ? $def : $v;
}

function httpPost(string $url, string $body, array $headers, int $timeout = 120): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        throw new RuntimeException('HTTP hiba: ' . $err);
    }
    return [$code, (string) $resp];
}

/** Az első [...] JSON-tömb kinyerése a szövegből. */
function extractJsonArray(string $s): string
{
    $s = trim($s);
    $start = strpos($s, '[');
    $end   = strrpos($s, ']');
    if ($start === false || $end === false || $end < $start) {
        return '[]';
    }
    return substr($s, $start, $end - $start + 1);
}

/** Programoldal letöltése és sima szöveggé alakítása (méret-korlátozott). */
function fetchUrlText(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_USERAGENT      => 'holborozzakBot/1.0 (+https://holborozzak.hu)',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $html = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $code >= 400) {
        throw new RuntimeException('letöltés sikertelen (HTTP ' . $code . ')');
    }

    $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', (string) $html) ?? $html;
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);
    if (mb_strlen($text) > 14000) {
        $text = mb_substr($text, 0, 14000); // token-korlát
    }
    if ($text === '') {
        throw new RuntimeException('üres szöveg');
    }
    return $text;
}

/**
 * Egy programoldal szövegéből a Claude-dal kinyeri az ÖSSZES közelgő eseményt (JSON tömb).
 * $hint: a helyszínre jellemző alap-infó (város/helyszín/borvidék), ha az oldal nem írja ki.
 */
function extractEventsFromPage(string $apiKey, string $model, string $pageText, string $sourceUrl, string $hint, string $today): array
{
    $system = "Magyar borrendezvény-adatkinyerő vagy. A megadott programoldal-szövegből nyerd ki az ÖSSZES "
        . "KÜLÖNÁLLÓ, KÖZELGŐ (a mai naptól számított kb. 9 hónapon belüli) borhoz köthető eseményt "
        . "(borvacsora, borest, borkóstoló, portfólió-kóstoló, bornap, szüret, nyitott pince). "
        . "KIZÁRÓLAG egyetlen JSON TÖMBÖT adj vissza (markdown és magyarázat NÉLKÜL); minden elem: "
        . "{title, start_datetime, end_datetime, city, venue_name, region_name, website_url, source_url, short_description, image_url}.\n"
        . "Dátumformátum 'YYYY-MM-DDTHH:MM:SS' (ismeretlen idő: 00:00:00). CSAK olyan eseményt adj vissza, "
        . "aminek VAN konkrét, a mai napnál (vagy azzal egyenlő) NEM korábbi kezdődátuma — a múltbeli és a "
        . "dátum nélküli tételeket HAGYD KI. Ha az oldal nem ír várost/helyszínt/borvidéket, használd ezt az "
        . "alap-infót: {$hint}. A region_name a 22 magyar borvidék egyike legyen, ha azonosítható, különben üres. "
        . "Soha ne találj ki adatot. Ha nincs érvényes esemény, üres tömböt ([]) adj vissza.";

    $user = "Mai dátum: {$today}. Forrás URL: {$sourceUrl}\n\nOldal szövege:\n{$pageText}";

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 3000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('AI hívás hiba: ' . $err);
    }
    $data = json_decode((string) $resp, true);
    if ($code >= 400) {
        throw new RuntimeException('AI hiba: ' . ($data['error']['message'] ?? ('HTTP ' . $code)));
    }
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= (string) $block['text'];
        }
    }
    $items = json_decode(extractJsonArray($text), true);
    return is_array($items) ? $items : [];
}

// ---------------------------------------------------------------------------
// Kurált forráslista: konkrét programoldalak + a helyszínre jellemző alap-infó.
// Bővíthető: elég egy új sort felvenni. A source_url mindig a beolvasott oldal lesz.

$SOURCES = [
    ['url' => 'https://jardinette.hu/programok/',
     'hint' => 'Jardinette Kertvendéglő, Budapest'],
    ['url' => 'https://www.laposa.hu/hu/borvacsora',
     'hint' => 'Laposa Birtok „Tűz és Kő” borvacsora, Badacsony, Badacsonyi borvidék'],
    ['url' => 'https://winehub.hu/en/events/',
     'hint' => 'WineHub – Wine Connects borközpont, Budapest'],
    ['url' => 'https://www.wineloversrendezvenyek.hu/',
     'hint' => 'Winelovers Rendezvények, Budapest'],
    ['url' => 'https://www.programturizmus.hu/ajanlat-borvacsora-gasztronomai-borkostolo-programok.html',
     'hint' => 'borvacsora/borest, Magyarország'],
    ['url' => 'https://www.programturizmus.hu/ajanlat-budapesti-borkostolo.html',
     'hint' => 'borkóstoló, Budapest'],
    ['url' => 'https://badacsony.com/events/details/3332-borbarangolas-borvacsorak-borbaratok-etterem',
     'hint' => 'Borbarátok Étterem (Borbarangolás borvacsorák), Badacsony, Badacsonyi borvidék'],
];

$apiKey       = envv('ANTHROPIC_API_KEY');
$model        = envv('ANTHROPIC_MODEL', 'claude-haiku-4-5');
$collectUrl   = envv('COLLECT_URL');
$collectToken = envv('COLLECT_TOKEN');

if ($apiKey === '') {
    fwrite(STDERR, "Hiányzik az ANTHROPIC_API_KEY.\n");
    exit(1);
}
if ($collectUrl === '' || $collectToken === '') {
    fwrite(STDERR, "Hiányzik a COLLECT_URL vagy a COLLECT_TOKEN.\n");
    exit(1);
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');

$all = [];
foreach ($SOURCES as $src) {
    $url  = $src['url'];
    $hint = $src['hint'];
    echo "[" . date('c') . "] Forrás: {$url}\n";
    try {
        $pageText = fetchUrlText($url);
    } catch (Throwable $e) {
        fwrite(STDERR, "  ! letöltés kihagyva: " . $e->getMessage() . "\n");
        continue;
    }
    try {
        $items = extractEventsFromPage($apiKey, $model, $pageText, $url, $hint, $today);
    } catch (Throwable $e) {
        fwrite(STDERR, "  ! kinyerés hiba: " . $e->getMessage() . "\n");
        continue;
    }
    $n = 0;
    foreach ($items as $it) {
        if (!is_array($it) || trim((string) ($it['title'] ?? '')) === '') {
            continue;
        }
        $it['source_url'] = $url; // a jelölt forrása mindig a beolvasott programoldal
        $all[] = $it;
        $n++;
    }
    echo "  → {$n} esemény kinyerve\n";
}

echo "[" . date('c') . "] Összes kinyert esemény: " . count($all) . " — beküldés a weboldalra…\n";
if (count($all) === 0) {
    echo "Nincs beküldendő esemény.\n";
    exit(0);
}

try {
    $payload = json_encode(['token' => $collectToken, 'events' => $all], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    [$code, $resp] = httpPost($collectUrl, $payload, [
        'content-type: application/json',
        'x-collect-token: ' . $collectToken,
    ]);
    if ($code >= 400) {
        fwrite(STDERR, "Ingest hiba (HTTP {$code}): {$resp}\n");
        exit(1);
    }
    $r = json_decode($resp, true);
    $added   = (int) ($r['added'] ?? 0);
    $skipped = (int) ($r['skipped'] ?? 0);
    echo "[" . date('c') . "] Kész. Új jelölt: {$added}, kihagyott (duplikált): {$skipped}.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Beküldés hiba: ' . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
