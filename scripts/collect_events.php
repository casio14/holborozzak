<?php
declare(strict_types=1);

/**
 * Napi esemény-gyűjtő (GitHub Actions).
 *
 * A Claude `web_search` eszközével KERES az interneten közelgő, magyar borhoz
 * köthető eseményeket (nem előre megadott oldalakat néz), dedupál az adatbázissal,
 * és az újakat 'new' státuszú jelöltként beszúrja az event_candidates táblába.
 * A jóváhagyás az adminban kézzel történik.
 *
 * Env (GitHub secret/var): DB_PASSWORD, ANTHROPIC_API_KEY, ANTHROPIC_MODEL (opcionális).
 * NEM kerül a webszerverre — csak a CI futtatja.
 */

require __DIR__ . '/../public/lib/candidates.php'; // ez behúzza az events.php-t is

function envv(string $k, string $def = ''): string
{
    $v = getenv($k);
    return $v === false ? $def : $v;
}

function httpPost(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 120,
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

/** Claude web search hívás (pause_turn-kezeléssel) → záró szöveg. */
function searchEventsViaClaude(string $apiKey, string $model, string $system, string $userText): string
{
    $messages = [['role' => 'user', 'content' => $userText]];
    $tools = [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 8]];
    $headers = [
        'content-type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ];

    for ($i = 0; $i < 8; $i++) {
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 4000,
            'system'     => $system,
            'messages'   => $messages,
            'tools'      => $tools,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        [$code, $resp] = httpPost('https://api.anthropic.com/v1/messages', $payload, $headers);
        $data = json_decode($resp, true);
        if ($code >= 400) {
            throw new RuntimeException('AI hiba: ' . ($data['error']['message'] ?? ('HTTP ' . $code)));
        }

        $messages[] = ['role' => 'assistant', 'content' => $data['content'] ?? []];

        if (($data['stop_reason'] ?? '') === 'pause_turn') {
            continue; // szerveroldali eszköz fut tovább — újraküldjük
        }

        $text = '';
        foreach (($data['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= (string) $b['text'];
            }
        }
        return $text;
    }
    return '';
}

// ---------------------------------------------------------------------------

$apiKey = envv('ANTHROPIC_API_KEY');
$model  = envv('ANTHROPIC_MODEL', 'claude-haiku-4-5');
$dbPass = envv('DB_PASSWORD');
if ($apiKey === '') {
    fwrite(STDERR, "Hiányzik az ANTHROPIC_API_KEY.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        'mysql:host=mysql.rackhost.hu;port=3306;dbname=c105746holborozzak;charset=utf8mb4',
        'c105746ptrk',
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'DB kapcsolat hiba: ' . $e->getMessage() . "\n");
    exit(1);
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');

$system = "Magyar borrendezvény-kutató vagy. A web_search eszközzel KERESS az interneten "
    . "KÖZELGŐ (a mai naptól számított kb. 6 hónapon belüli) magyarországi, borhoz köthető "
    . "eseményeket: borfesztiválok, bornapok, szüreti rendezvények, kóstolók, pincék programjai. "
    . "Futtass több, változatos keresést (különböző borvidékek, hónapok, rendezvénytípusok).\n"
    . "A végén KIZÁRÓLAG egyetlen JSON TÖMBÖT adj vissza (markdown és magyarázat nélkül), ahol minden elem: "
    . "{title, start_datetime, end_datetime, city, venue_name, region_name, website_url, source_url, short_description}.\n"
    . "Dátumformátum: 'YYYY-MM-DDTHH:MM:SS' (ismeretlen idő: 00:00:00); ha nincs adat, üres string. "
    . "A region_name a 22 magyar borvidék egyike legyen, ha azonosítható. A source_url az az oldal, ahol az "
    . "esemény megerősítve szerepel. CSAK valós, forrással alátámasztott eseményeket adj vissza — soha ne találj ki adatot.";

$user = "Mai dátum: {$today}. Keress legalább 10-15 közelgő, valós magyar borrendezvényt, és add vissza a JSON tömböt.";

echo "[" . date('c') . "] Keresés indul (model={$model})…\n";

try {
    $text = searchEventsViaClaude($apiKey, $model, $system, $user);
} catch (Throwable $e) {
    fwrite(STDERR, 'Keresés hiba: ' . $e->getMessage() . "\n");
    exit(1);
}

$items = json_decode(extractJsonArray($text), true);
if (!is_array($items)) {
    fwrite(STDERR, "Nem sikerült értelmezni a választ JSON-ként.\n");
    exit(1);
}

$insSql = "INSERT INTO event_candidates
    (source_url, title, short_description, start_datetime, end_datetime,
     venue_name, city, region_name, website_url, dedup_key, status)
   VALUES
    (:src, :title, :short, :start, :end,
     :venue, :city, :region, :web, :dedup, 'new')";
$ins = $pdo->prepare($insSql);

$found = 0;
$added = 0;
$skipped = 0;
foreach ($items as $d) {
    if (!is_array($d)) {
        continue;
    }
    $title = trim((string) ($d['title'] ?? ''));
    if ($title === '') {
        continue;
    }
    $found++;

    $start = toMysqlDatetime((string) ($d['start_datetime'] ?? ''));
    $city  = trim((string) ($d['city'] ?? ''));
    $dedup = candidateDedupKey($title, $start, $city);

    if (candidateDuplicate($pdo, $dedup) || eventDuplicate($pdo, $title, $start, $city)) {
        $skipped++;
        continue;
    }

    $ins->execute([
        ':src'    => ($d['source_url'] ?? '') ?: ($d['website_url'] ?? '') ?: null,
        ':title'  => $title,
        ':short'  => ($d['short_description'] ?? '') ?: null,
        ':start'  => $start,
        ':end'    => toMysqlDatetime((string) ($d['end_datetime'] ?? '')),
        ':venue'  => ($d['venue_name'] ?? '') ?: null,
        ':city'   => $city !== '' ? $city : null,
        ':region' => ($d['region_name'] ?? '') ?: null,
        ':web'    => ($d['website_url'] ?? '') ?: null,
        ':dedup'  => $dedup,
    ]);
    $added++;
    echo "  + {$title}\n";
}

echo "[" . date('c') . "] Kész. Talált: {$found}, új jelölt: {$added}, kihagyott (duplikált): {$skipped}.\n";
exit(0);
