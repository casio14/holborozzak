<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél-sablonok: üdvözlő levél + kéthetenkénti esemény-összefoglaló.
// Egységes „bordó fejléces" stílus: sötét banner-fejléc arany logóval, a kiemeltek
// arany keretes díszdobozban, dátum-kockás eseménylista, sötét lábléc.
// Inline stílusok (a levelezők nem töltenek külső CSS-t); minden escape itt,
// htmlspecialchars-szal — nem függ a lib/events.php-tól.
//
// Esemény-tétel mezői: title, url, date, city, free, day (napszám), mon (hó-rövidítés),
// digestnél opcionálisan featured.

/** Közös levél-váz: bordó fejléc (logó + cím) + tartalom + sötét lábléc. */
function nlBordoWrap(string $headerTitle, string $inner, string $unsubUrl): string
{
    $u = htmlspecialchars($unsubUrl, ENT_QUOTES);
    return '<!doctype html><html lang="hu"><body style="margin:0;padding:0;background:#efe9df;">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 12px;font-family:Georgia,\'Times New Roman\',serif;color:#2b1d20;">'
        . '<div style="border-radius:14px;overflow:hidden;border:1px solid #e0d5c5;">'
        // Fejléc-banner (Outlook nem tud gradienst — az egyszínű bordó a fallback)
        . '<div style="background:#4a0e1c;background:linear-gradient(135deg,#722f37,#4a0e1c);text-align:center;padding:26px 20px 22px;">'
        . '<span style="font-size:26px;font-weight:bold;color:#e3cd97;">hol<span style="color:#ffffff;">borozzak</span>.hu</span><br>'
        . '<span style="font-size:11px;letter-spacing:3px;color:#c8a14b;font-family:Arial,sans-serif;">BORRENDEZVÉNYEK EGY HELYEN</span>'
        . '<div style="padding-top:14px;"><span style="color:#ffffff;font-size:20px;">' . $headerTitle . '</span></div>'
        . '</div>'
        . '<div style="background:#fffdf9;padding:22px;">' . $inner . '</div>'
        // Sötét lábléc
        . '<div style="background:#2f0d16;text-align:center;padding:14px;font-size:12px;color:#a8908a;font-family:Arial,sans-serif;line-height:1.6;">'
        . 'Ezt a levelet azért kaptad, mert feliratkoztál a holborozzak.hu hírlevelére.<br>'
        . '<a href="' . $u . '" style="color:#e3cd97;">Leiratkozás egy kattintással</a>'
        . '</div>'
        . '</div></div></body></html>';
}

/** Arany gomb, középre zárva. */
function nlGoldButton(string $url, string $label): string
{
    return '<div style="text-align:center;padding-top:18px;">'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" '
        . 'style="display:inline-block;background:#c8a14b;color:#4a0e1c;font-family:Arial,sans-serif;'
        . 'font-weight:bold;font-size:15px;text-decoration:none;padding:12px 26px;border-radius:10px;">'
        . htmlspecialchars($label) . '</a></div>';
}

/** Kiemelt események — arany keretes díszdoboz. */
function nlFeaturedBoxHtml(array $featured): string
{
    $html = '<div style="border:2px solid #c8a14b;border-radius:12px;padding:14px 16px;margin-bottom:20px;background:#fdf8ee;">'
        . '<p style="margin:0 0 10px;font-size:12px;font-weight:bold;letter-spacing:2px;color:#a07f34;font-family:Arial,sans-serif;text-align:center;">★ KIEMELT ESEMÉNYEK ★</p>';
    $last = count($featured) - 1;
    foreach ($featured as $i => $it) {
        $border = $i === $last ? '' : 'border-bottom:1px solid #ecdfc2;';
        $html .= '<div style="padding:8px 0;' . $border . '">'
            . '<a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" style="font-weight:bold;font-size:17px;color:#4a0e1c;text-decoration:none;font-family:Georgia,serif;">'
            . htmlspecialchars($it['title']) . '</a><br>'
            . '<span style="font-family:Arial,sans-serif;font-size:13px;color:#722f37;font-weight:bold;">' . htmlspecialchars($it['date']) . '</span>'
            . ($it['city'] !== ''
                ? '<span style="font-family:Arial,sans-serif;font-size:13px;color:#8a7d77;"> · 📍 ' . htmlspecialchars($it['city']) . '</span>'
                : '')
            . '</div>';
    }
    return $html . '</div>';
}

/** Eseménylista dátum-kockákkal (táblázatos — minden levelezőben stabil). */
function nlDateRowsHtml(array $items): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        $border = $i === $last ? '' : 'border-bottom:1px solid #e7ddcf;';
        // A kockában a kezdőnap; több naposnál a sor alatt a teljes intervallum is.
        $isRange = strpos($it['date'], '–') !== false;
        $sub = ($isRange ? htmlspecialchars($it['date']) . ' · ' : '') . htmlspecialchars($it['city']);
        $freePill = $it['free']
            ? ' <span style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;color:#3f5a2a;background:#e6efe0;border-radius:8px;padding:1px 7px;">INGYENES</span>'
            : '';
        $html .= '<tr>'
            . '<td style="width:52px;padding:7px 10px 7px 0;vertical-align:top;">'
            . '<div style="background:#f7f2ea;border:1px solid #e7ddcf;border-radius:8px;text-align:center;padding:4px 0;">'
            . '<span style="display:block;font-family:Georgia,serif;font-weight:bold;font-size:17px;color:#4a0e1c;">' . htmlspecialchars($it['day']) . '</span>'
            . '<span style="display:block;font-family:Arial,sans-serif;font-size:9px;font-weight:bold;color:#722f37;">' . htmlspecialchars($it['mon']) . '</span>'
            . '</div></td>'
            . '<td style="padding:7px 0;' . $border . '">'
            . '<a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" style="font-weight:bold;font-size:15px;color:#4a0e1c;text-decoration:none;font-family:Georgia,serif;">'
            . htmlspecialchars($it['title']) . '</a>' . $freePill . '<br>'
            . '<span style="font-family:Arial,sans-serif;font-size:12px;color:#8a7d77;">' . $sub . '</span>'
            . '</td></tr>';
    }
    return $html . '</table>';
}

/**
 * Üdvözlő levél feliratkozás után.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool,day:string,mon:string}> $featured
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool,day:string,mon:string}> $monthItems
 * @param int $moreCount ennyi további esemény maradt ki a havi listából (0 = semmi)
 */
function nlWelcomeHtml(string $listUrl, string $unsubUrl, array $featured = [], array $monthItems = [], int $moreCount = 0): string
{
    $inner = '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;font-family:Arial,sans-serif;color:#4a3b36;">'
        . 'Köszönjük, hogy feliratkoztál! <b>Kéthetente</b> jelentkezünk a közelgő magyar '
        . 'borrendezvényekkel. Kezdésnek íme a kínálat:</p>';

    if ($featured) {
        $inner .= nlFeaturedBoxHtml($featured);
    }

    if ($monthItems) {
        $inner .= '<p style="margin:0 0 10px;font-size:12px;font-weight:bold;letter-spacing:2px;color:#722f37;font-family:Arial,sans-serif;">📅 A KÖVETKEZŐ EGY HÓNAP</p>'
            . nlDateRowsHtml($monthItems);
        if ($moreCount > 0) {
            $inner .= '<p style="margin:10px 0 0;font-size:13px;color:#8a7d77;font-family:Arial,sans-serif;text-align:center;">'
                . '…és még ' . (int) $moreCount . ' esemény a hónapban.</p>';
        }
    }

    $inner .= nlGoldButton($listUrl, 'Összes esemény →');
    return nlBordoWrap('Üdv a borkedvelők közt! 🍷', $inner, $unsubUrl);
}

/**
 * Kéthetenkénti összefoglaló — a kiemeltek az arany díszdobozba kerülnek,
 * a többi esemény dátum-kockás listába.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool,day:string,mon:string,featured?:bool}> $items
 */
function nlDigestHtml(array $items, string $listUrl, string $unsubUrl): string
{
    $featured = array_values(array_filter($items, static fn(array $it): bool => !empty($it['featured'])));
    $rest     = array_values(array_filter($items, static fn(array $it): bool => empty($it['featured'])));

    $inner = '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;font-family:Arial,sans-serif;color:#4a3b36;">'
        . 'Íme a következő hetek borrendezvényei — kattints a részletekért!</p>';

    if ($featured) {
        $inner .= nlFeaturedBoxHtml($featured);
    }

    if ($rest) {
        $inner .= '<p style="margin:0 0 10px;font-size:12px;font-weight:bold;letter-spacing:2px;color:#722f37;font-family:Arial,sans-serif;">📅 A KÖVETKEZŐ HETEK</p>'
            . nlDateRowsHtml($rest);
    }

    $inner .= nlGoldButton($listUrl, 'Összes esemény →');
    return nlBordoWrap('Közelgő borrendezvények 🍷', $inner, $unsubUrl);
}
