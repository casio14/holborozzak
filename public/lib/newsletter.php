<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél-sablonok: üdvözlő levél + kéthetenkénti esemény-összefoglaló.
// Egységes „minimál-elegáns" (borlap) stílus: kép nélküli, tipográfia-központú,
// finoman sötétebb pergamen háttérrel. Inline stílusok (a levelezők nem töltenek
// külső CSS-t); minden escape itt, htmlspecialchars-szal — nem függ a lib/events.php-tól.

/** Közös levél-váz: pergamen háttér, középre zárt hasáb, logó + záró lábléc. */
function nlMinimalWrap(string $inner, string $unsubUrl): string
{
    $orn = '<div style="text-align:center;color:#c8a14b;font-size:14px;letter-spacing:6px;padding:0 0 18px;">&#10086;</div>';
    return '<!doctype html><html lang="hu"><body style="margin:0;padding:0;background:#f4ede0;">'
        . '<div style="max-width:520px;margin:0 auto;padding:30px 16px;font-family:Georgia,\'Times New Roman\',serif;color:#2b1d20;">'
        . '<div style="text-align:center;padding-bottom:6px;">'
        . '<span style="font-size:22px;font-weight:bold;color:#722f37;">hol<span style="color:#4a0e1c;">borozzak</span>.hu</span></div>'
        . $orn
        . $inner
        . '<div style="text-align:center;color:#c8a14b;font-size:14px;letter-spacing:6px;padding:22px 0 10px;">&#10086;</div>'
        . '<p style="text-align:center;font-size:12px;color:#857468;font-family:Arial,sans-serif;margin:0;line-height:1.6;">'
        . 'Ezt a levelet azért kaptad, mert feliratkoztál a holborozzak.hu hírlevelére.<br>'
        . '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES) . '" style="color:#722f37;">Leiratkozás egy kattintással</a></p>'
        . '</div></body></html>';
}

/** Aláhúzásos „gomb"-link, középre zárva. */
function nlLinkButton(string $url, string $label): string
{
    return '<div style="text-align:center;padding-top:22px;">'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" '
        . 'style="display:inline-block;color:#722f37;font-family:Georgia,serif;font-weight:bold;font-size:16px;'
        . 'text-decoration:none;border-bottom:2px solid #c8a14b;padding-bottom:2px;">'
        . htmlspecialchars($label) . '</a></div>';
}

/**
 * Műsorfüzet-lista: cím + város balra, dátum jobbra; kiemelt tétel ★-gal.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool,featured?:bool}> $items
 */
function nlEventListHtml(array $items): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        $border = $i === $last ? '' : 'border-bottom:1px dotted #cfc2ae;';
        $star = !empty($it['featured']) ? '<span style="color:#c8a14b;">★</span> ' : '';
        $html .= '<tr>'
            . '<td style="padding:8px 0;' . $border . '">'
            . $star
            . '<a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" style="color:#2b1d20;font-weight:bold;font-size:15px;text-decoration:none;">'
            . htmlspecialchars($it['title']) . '</a><br>'
            . '<span style="font-family:Arial,sans-serif;font-size:12px;color:#857468;">'
            . htmlspecialchars($it['city']) . ($it['free'] ? ($it['city'] !== '' ? ' · ' : '') . 'ingyenes' : '')
            . '</span></td>'
            . '<td style="padding:8px 0;' . $border . 'text-align:right;vertical-align:top;white-space:nowrap;">'
            . '<span style="font-family:Arial,sans-serif;font-size:13px;color:#722f37;font-weight:bold;">'
            . htmlspecialchars($it['date']) . '</span></td>'
            . '</tr>';
    }
    return $html . '</table>';
}

/** Kiemelt események blokkja — arany vonalak közt, csillaggal, középre zárva. */
function nlFeaturedBlockHtml(array $featured): string
{
    $html = '<div style="border-top:2px solid #c8a14b;border-bottom:2px solid #c8a14b;padding:14px 4px;margin-bottom:22px;">'
        . '<p style="margin:0 0 10px;font-size:11px;font-weight:bold;letter-spacing:3px;color:#a07f34;font-family:Arial,sans-serif;text-align:center;">KIEMELT ESEMÉNYEK</p>';
    foreach ($featured as $i => $it) {
        $html .= '<p style="margin:0 0 ' . ($i === count($featured) - 1 ? '0' : '10px') . ';font-size:18px;text-align:center;line-height:1.5;">'
            . '★ <a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" style="color:#4a0e1c;font-weight:bold;text-decoration:none;">'
            . htmlspecialchars($it['title']) . '</a><br>'
            . '<span style="font-family:Arial,sans-serif;font-size:13px;color:#722f37;">'
            . htmlspecialchars($it['date'])
            . ($it['city'] !== '' ? ' · ' . htmlspecialchars($it['city']) : '')
            . '</span></p>';
    }
    return $html . '</div>';
}

/**
 * Üdvözlő levél feliratkozás után.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool}> $featured
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool}> $monthItems
 * @param int $moreCount ennyi további esemény maradt ki a havi listából (0 = semmi)
 */
function nlWelcomeHtml(string $listUrl, string $unsubUrl, array $featured = [], array $monthItems = [], int $moreCount = 0): string
{
    $inner = '<h1 style="margin:0 0 14px;font-size:24px;color:#4a0e1c;text-align:center;">Üdv a borkedvelők közt!</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4a3b36;text-align:center;">'
        . 'Köszönjük, hogy feliratkoztál. Kéthetente küldünk egy rövid, reklámmentes levelet '
        . 'a közelgő magyar borrendezvényekről — íme az első válogatás.</p>';

    if ($featured) {
        $inner .= nlFeaturedBlockHtml($featured);
    }

    if ($monthItems) {
        $inner .= '<p style="margin:0 0 10px;font-size:11px;font-weight:bold;letter-spacing:3px;color:#722f37;font-family:Arial,sans-serif;text-align:center;">A KÖVETKEZŐ EGY HÓNAP</p>'
            . nlEventListHtml($monthItems);
        if ($moreCount > 0) {
            $inner .= '<p style="margin:10px 0 0;font-size:13px;color:#857468;font-family:Arial,sans-serif;text-align:center;">'
                . '…és még ' . (int) $moreCount . ' esemény a hónapban.</p>';
        }
    }

    $inner .= nlLinkButton($listUrl, 'Összes esemény megtekintése →');
    return nlMinimalWrap($inner, $unsubUrl);
}

/**
 * Kéthetenkénti összefoglaló a közelgő eseményekről — a kiemeltek ★-gal jelölve.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool,featured?:bool}> $items
 */
function nlDigestHtml(array $items, string $listUrl, string $unsubUrl): string
{
    $inner = '<h1 style="margin:0 0 14px;font-size:24px;color:#4a0e1c;text-align:center;">Közelgő borrendezvények</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#4a3b36;text-align:center;">'
        . 'A következő hetek programjai — kattints a részletekért! '
        . 'A <span style="color:#c8a14b;">★</span> a kiemelt eseményeket jelöli.</p>'
        . nlEventListHtml($items)
        . nlLinkButton($listUrl, 'Összes esemény megtekintése →');
    return nlMinimalWrap($inner, $unsubUrl);
}
