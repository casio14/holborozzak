<?php
declare(strict_types=1);

// holborozzak.hu — egyszerű HTML e-mail küldés (PHP mail(), Rackhost szerverről).
// A feladó a config.php 'mail' szekciójából jön (éles: MAIL_FROM secret);
// ha nincs beállítva, a kiszolgáló hosztjából képzett no-reply cím a fallback.

function mailConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config.php';
        $all = is_file($file) ? require $file : [];
        $cfg = (array) ($all['mail'] ?? []);
    }
    return $cfg;
}

/**
 * HTML levél küldése. Visszatérés: a mail() eredménye (a tényleges kézbesítést
 * nem garantálja, csak az átadást a helyi MTA-nak).
 *
 * @param array<string,string> $extraHeaders pl. ['List-Unsubscribe' => '<url>']
 */
function sendMailHtml(string $to, string $subject, string $html, array $extraHeaders = []): bool
{
    $cfg = mailConfig();
    $fromEmail = (string) ($cfg['from_email'] ?? '');
    if ($fromEmail === '') {
        $host = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $fromEmail = 'no-reply@' . ($host !== '' ? $host : 'kissptrk.hu');
    }
    $fromName = (string) ($cfg['from_name'] ?? 'holborozzak.hu');

    $headers = array_merge([
        'MIME-Version'  => '1.0',
        'Content-Type'  => 'text/html; charset=UTF-8',
        'From'          => mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
        'Reply-To'      => $fromEmail,
    ], $extraHeaders);

    $lines = [];
    foreach ($headers as $k => $v) {
        $lines[] = $k . ': ' . $v;
    }

    // A 998 karakternél hosszabb sorokat az MTA-k kényszerrel tördelik — akár szó
    // vagy style-attribútum közepén, ami széttöri a megjelenést. Ezért szóközöknél
    // magunk tördelünk max 500 karakteres sorokra (HTML-ben ez veszélytelen:
    // a sortörés szóköznek számít a szövegben és az attribútumokban is).
    $html = wordwrap($html, 500, "\n", false);

    // Boríték-feladó (Return-Path) igazítása a From címhez: enélkül a szerver
    // rendszerfelhasználója lenne a boríték-feladó, a DMARC/SPF igazodás elhasal,
    // és a levél spam-be megy. Csak biztonságos címet adunk át a shellnek.
    $extra = '';
    if (preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$/', $fromEmail)) {
        $extra = '-f' . $fromEmail;
    }

    return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $html, implode("\r\n", $lines), $extra);
}
