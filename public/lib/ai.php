<?php
declare(strict_types=1);

/**
 * Esemény-adatok kinyerése weboldalból a Claude (Anthropic) API-val.
 * Sima cURL (a projekt build nélküli) → Messages API.
 */

/** Az anthropic config szekció (api_key, model). */
function aiConfig(): array
{
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) {
        $c = require $cfg;
        return is_array($c['anthropic'] ?? null) ? $c['anthropic'] : [];
    }
    return [];
}

/** Weboldal letöltése és sima szöveggé alakítása (udvarias, méret-korlátozott). */
function fetchUrlText(string $url): string
{
    if (!preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('Érvénytelen URL (http/https kell).');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'holborozzakBot/1.0 (+https://holborozzak.hu)',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $html = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false) {
        throw new RuntimeException('Az oldal letöltése nem sikerült: ' . $err);
    }
    if ($code >= 400) {
        throw new RuntimeException('A forrás HTTP ' . $code . ' hibát adott.');
    }

    // script/style eltávolítása, majd tag-mentes szöveg
    $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', (string) $html) ?? $html;
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);
    if (mb_strlen($text) > 12000) {
        $text = mb_substr($text, 0, 12000); // token-korlát
    }
    if ($text === '') {
        throw new RuntimeException('Az oldalból nem sikerült szöveget kinyerni.');
    }
    return $text;
}

/**
 * Az oldalszövegből esemény-JSON-t kér a Claude-tól.
 * Visszaad egy asszociatív tömböt a kinyert mezőkkel (found, title, …).
 */
function aiExtractEvent(string $pageText, string $sourceUrl): array
{
    $cfg   = aiConfig();
    $key   = (string) ($cfg['api_key'] ?? '');
    $model = (string) ($cfg['model'] ?? '');
    if ($model === '') {
        $model = 'claude-opus-4-8';
    }
    if ($key === '') {
        throw new RuntimeException('Nincs Anthropic API kulcs beállítva (ANTHROPIC_API_KEY secret / config.php).');
    }

    $system = "Magyar borrendezvény-adatkinyerő vagy. A megadott weboldal-szövegből nyerd ki EGY konkrét "
        . "esemény adatait, és KIZÁRÓLAG egyetlen JSON objektumot adj vissza — markdown, kódkeret és magyarázat NÉLKÜL.\n"
        . "Kötelező mezők (mind szerepeljen): found (bool), title, short_description, description, start_datetime, "
        . "end_datetime, venue_name, city, region_name, website_url, facebook_url, ticket_url, is_free (bool), price_info, image_url.\n"
        . "A dátumok formátuma 'YYYY-MM-DDTHH:MM:SS' (ha az időpont ismeretlen, 00:00:00); ha nincs adat, üres string (\"\").\n"
        . "A region_name a 22 magyar borvidék egyike legyen, ha azonosítható (pl. Tokaji, Egri, Villányi, Badacsonyi), különben üres.\n"
        . "found legyen false, ha a szöveg nem egy konkrét, magyar borhoz köthető rendezvény. Kizárólag tényszerű "
        . "adatokat adj vissza; soha ne találj ki adatot.";

    $user = "Forrás URL: {$sourceUrl}\n\nOldal szövege:\n{$pageText}";

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 2000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Az AI hívás nem sikerült: ' . $err);
    }
    $data = json_decode((string) $resp, true);
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $code);
        throw new RuntimeException('AI hiba: ' . $msg);
    }

    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= (string) $block['text'];
        }
    }
    $parsed = json_decode(extractJsonObject($text), true);
    if (!is_array($parsed)) {
        throw new RuntimeException('Az AI válasza nem volt értelmezhető JSON.');
    }
    return $parsed;
}

/** Kiveszi az első {...} JSON-blokkot (markdown-kódkeret tolerancia). */
function extractJsonObject(string $s): string
{
    $s = trim($s);
    $start = strpos($s, '{');
    $end   = strrpos($s, '}');
    if ($start === false || $end === false || $end < $start) {
        return $s;
    }
    return substr($s, $start, $end - $start + 1);
}
