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

/**
 * Üdvözlő levél feliratkozás után — „minimál-elegáns" (borlap) stílus:
 * kép nélküli, tipográfia-központú; kiemeltek arany vonalak közt csillaggal,
 * a következő egy hónap eseményei műsorfüzet-listában (cím balra, dátum jobbra).
 *
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool}> $featured
 * @param array<int,array{title:string,url:string,date:string,city:string,free:bool}> $monthItems
 * @param int $moreCount ennyi további esemény maradt ki a havi listából (0 = semmi)
 */
function nlWelcomeHtml(string $listUrl, string $unsubUrl, array $featured = [], array $monthItems = [], int $moreCount = 0): string
{
    $orn = '<div style="text-align:center;color:#c8a14b;font-size:14px;letter-spacing:6px;padding-bottom:18px;">&#10086;</div>';

    $html = '<div style="text-align:center;padding-bottom:6px;">'
        . '<span style="font-size:22px;font-weight:bold;color:#722f37;">hol<span style="color:#4a0e1c;">borozzak</span>.hu</span></div>'
        . $orn
        . '<h1 style="margin:0 0 14px;font-size:24px;color:#4a0e1c;text-align:center;">Üdv a borkedvelők közt!</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4a3b36;text-align:center;">'
        . 'Köszönjük, hogy feliratkoztál. Kéthetente küldünk egy rövid, reklámmentes levelet '
        . 'a közelgő magyar borrendezvényekről — íme az első válogatás.</p>';

    // Kiemelt események — arany vonalak közt, csillaggal
    if ($featured) {
        $html .= '<div style="border-top:2px solid #c8a14b;border-bottom:2px solid #c8a14b;padding:14px 4px;margin-bottom:22px;">'
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
        $html .= '</div>';
    }

    // A következő egy hónap eseményei — műsorfüzet-lista
    if ($monthItems) {
        $html .= '<p style="margin:0 0 10px;font-size:11px;font-weight:bold;letter-spacing:3px;color:#722f37;font-family:Arial,sans-serif;text-align:center;">A KÖVETKEZŐ EGY HÓNAP</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
        $last = count($monthItems) - 1;
        foreach ($monthItems as $i => $it) {
            $border = $i === $last ? '' : 'border-bottom:1px dotted #d9cfc0;';
            $html .= '<tr>'
                . '<td style="padding:8px 0;' . $border . '">'
                . '<a href="' . htmlspecialchars($it['url'], ENT_QUOTES) . '" style="color:#2b1d20;font-weight:bold;font-size:15px;text-decoration:none;">'
                . htmlspecialchars($it['title']) . '</a><br>'
                . '<span style="font-family:Arial,sans-serif;font-size:12px;color:#8a7d77;">'
                . htmlspecialchars($it['city']) . ($it['free'] ? ($it['city'] !== '' ? ' · ' : '') . 'ingyenes' : '')
                . '</span></td>'
                . '<td style="padding:8px 0;' . $border . 'text-align:right;vertical-align:top;white-space:nowrap;">'
                . '<span style="font-family:Arial,sans-serif;font-size:13px;color:#722f37;font-weight:bold;">'
                . htmlspecialchars($it['date']) . '</span></td>'
                . '</tr>';
        }
        $html .= '</table>';
        if ($moreCount > 0) {
            $html .= '<p style="margin:10px 0 0;font-size:13px;color:#8a7d77;font-family:Arial,sans-serif;text-align:center;">'
                . '…és még ' . (int) $moreCount . ' esemény a hónapban.</p>';
        }
    }

    $html .= '<div style="text-align:center;padding-top:22px;">'
        . '<a href="' . htmlspecialchars($listUrl, ENT_QUOTES) . '" '
        . 'style="display:inline-block;color:#722f37;font-family:Georgia,serif;font-weight:bold;font-size:16px;text-decoration:none;border-bottom:2px solid #c8a14b;padding-bottom:2px;">'
        . 'Összes esemény megtekintése →</a></div>'
        . '<div style="text-align:center;color:#c8a14b;font-size:14px;letter-spacing:6px;padding:22px 0 10px;">&#10086;</div>'
        . '<p style="text-align:center;font-size:12px;color:#8a7d77;font-family:Arial,sans-serif;margin:0;line-height:1.6;">'
        . 'Ezt a levelet azért kaptad, mert feliratkoztál a holborozzak.hu hírlevelére.<br>'
        . '<a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES) . '" style="color:#722f37;">Leiratkozás egy kattintással</a></p>';

    // Saját (kártya nélküli) váz — a minimál stílus nem dobozol
    return '<!doctype html><html lang="hu"><body style="margin:0;padding:0;background:#fffdf9;">'
        . '<div style="max-width:520px;margin:0 auto;padding:30px 16px;font-family:Georgia,\'Times New Roman\',serif;color:#2b1d20;">'
        . $html
        . '</div></body></html>';
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
