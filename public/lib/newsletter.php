<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél-sablonok: üdvözlő levél + kéthetenkénti esemény-összefoglaló.
// Inline-stílusos, egyszerű HTML (a levelezők nem töltenek külső CSS-t).
// Szándékosan nem függ a lib/events.php-tól: minden escape itt, htmlspecialchars-szal.

/** Közös levél-váz: fejléc-branding + tartalom-kártya + leiratkozó lábléc. */
function nlWrap(string $inner, string $unsubUrl): string
{
    $u = htmlspecialchars($unsubUrl, ENT_QUOTES);
    return '<!doctype html><html lang="hu"><body style="margin:0;padding:0;background:#f7f2ea;">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px;font-family:Georgia,\'Times New Roman\',serif;color:#2b1d20;">'
        . '<div style="text-align:center;padding-bottom:14px;">'
        . '<span style="font-size:22px;font-weight:bold;color:#722f37;">hol<span style="color:#4a0e1c;">borozzak</span>.hu</span><br>'
        . '<span style="font-size:11px;letter-spacing:2px;color:#8a7d77;font-family:Arial,sans-serif;">BORRENDEZVÉNYEK EGY HELYEN</span>'
        . '</div>'
        . '<div style="background:#fffdf9;border:1px solid #e7ddcf;border-radius:12px;padding:22px;">' . $inner . '</div>'
        . '<p style="text-align:center;font-size:12px;color:#8a7d77;font-family:Arial,sans-serif;padding-top:14px;line-height:1.6;">'
        . 'Ezt a levelet azért kaptad, mert feliratkoztál a holborozzak.hu hírlevelére.<br>'
        . '<a href="' . $u . '" style="color:#722f37;">Leiratkozás egy kattintással</a></p>'
        . '</div></body></html>';
}

/** Gomb-szerű link (levelezőbarát). */
function nlButton(string $url, string $label): string
{
    return '<div style="text-align:center;padding-top:16px;">'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" '
        . 'style="display:inline-block;background:#722f37;color:#f7f2ea;font-family:Arial,sans-serif;'
        . 'font-weight:bold;font-size:15px;text-decoration:none;padding:12px 26px;border-radius:10px;">'
        . htmlspecialchars($label) . '</a></div>';
}

/** Üdvözlő levél feliratkozás után. */
function nlWelcomeHtml(string $siteUrl, string $unsubUrl): string
{
    $inner = '<h1 style="margin:0 0 12px;font-size:22px;color:#4a0e1c;">Üdv a borkedvelők közt! 🍷</h1>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;font-family:Arial,sans-serif;color:#4a3b36;">'
        . 'Köszönjük, hogy feliratkoztál a holborozzak.hu hírlevelére!</p>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;font-family:Arial,sans-serif;color:#4a3b36;">'
        . '<b>Kéthetente</b> küldünk egy rövid levelet a közelgő magyar borrendezvényekről '
        . '— borfesztiválok, kóstolók, pincelátogatások és szüreti programok, Tokajtól Villányig.</p>'
        . '<p style="margin:0;font-size:15px;line-height:1.6;font-family:Arial,sans-serif;color:#4a3b36;">'
        . 'Addig is nézz körül az oldalon:</p>'
        . nlButton($siteUrl, 'Böngészem az eseményeket →');
    return nlWrap($inner, $unsubUrl);
}

/**
 * Kéthetenkénti összefoglaló a közelgő eseményekről.
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool}> $items
 */
function nlDigestHtml(array $items, string $listUrl, string $unsubUrl): string
{
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<div style="padding:12px 0;border-bottom:1px solid #e7ddcf;">'
            . '<a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" '
            . 'style="font-weight:bold;font-size:16px;color:#4a0e1c;text-decoration:none;">'
            . htmlspecialchars($it['title']) . '</a><br>'
            . '<span style="font-family:Arial,sans-serif;font-size:13px;color:#722f37;font-weight:bold;">'
            . htmlspecialchars($it['date']) . '</span>'
            . ($it['city'] !== ''
                ? '<span style="font-family:Arial,sans-serif;font-size:13px;color:#8a7d77;"> · 📍 ' . htmlspecialchars($it['city']) . '</span>'
                : '')
            . ($it['free']
                ? ' <span style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;color:#3f5a2a;background:#e6efe0;border-radius:8px;padding:1px 7px;">INGYENES</span>'
                : '')
            . '</div>';
    }

    $inner = '<h1 style="margin:0 0 6px;font-size:22px;color:#4a0e1c;">Közelgő borrendezvények 🍇</h1>'
        . '<p style="margin:0 0 8px;font-size:14px;line-height:1.6;font-family:Arial,sans-serif;color:#8a7d77;">'
        . 'A következő hetek programjai — kattints a részletekért!</p>'
        . $rows
        . nlButton($listUrl, 'Összes esemény →');
    return nlWrap($inner, $unsubUrl);
}
