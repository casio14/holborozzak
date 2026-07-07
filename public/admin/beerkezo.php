<?php
declare(strict_types=1);

// holborozzak.hu — admin: beérkező e-mailek (info@holborozzak.hu) IMAP-on olvasva.
// CSAK OLVASÁS: a szerveren a leveleket nem módosítja/törli, a flageket sem írja át.
// Hitelesítés a config.php 'imap' szekciójából (host/port/user/pass). Éles: IMAP_* secretek.

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php'; // h()
require_admin();

// Diagnosztika: belépett admin a ?debug=1-gyel láthatja a pontos hibaüzenetet.
if (($_GET['debug'] ?? '') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

/** IMAP config a config.php-ból. */
function imapConfig(): array
{
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) {
        $c = require $cfg;
        return is_array($c['imap'] ?? null) ? $c['imap'] : [];
    }
    return [];
}

/** MIME-kódolt fejléc (=?utf-8?…) dekódolása olvasható UTF-8-ra. */
function decodeMime(string $s): string
{
    $s = trim($s);
    if ($s === '' || !function_exists('imap_mime_header_decode')) {
        return $s;
    }
    $out = '';
    foreach (imap_mime_header_decode($s) as $part) {
        $cs  = strtoupper((string) $part->charset);
        $txt = (string) $part->text;
        if ($cs !== '' && $cs !== 'DEFAULT' && $cs !== 'UTF-8' && $cs !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($txt, 'UTF-8', $cs);
            if ($conv !== false) { $txt = $conv; }
        }
        $out .= $txt;
    }
    return $out;
}

/** Az első e-mail cím kinyerése egy From/Reply fejlécből (válasz-linkhez). */
function extractEmail(string $s): string
{
    return preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $s, $m) ? $m[0] : '';
}

/** Transzfer-kódolás visszafejtése (base64 / quoted-printable). */
function decodeBody(string $raw, int $encoding): string
{
    if ($encoding === 3) { return (string) base64_decode($raw); }            // BASE64
    if ($encoding === 4) { return (string) quoted_printable_decode($raw); }  // QUOTED-PRINTABLE
    return $raw;
}

/** Egy MIME-rész karakterkészletének UTF-8-ra alakítása. */
function partToUtf8(string $txt, object $part): string
{
    $cs = '';
    foreach (($part->parameters ?? []) as $pm) {
        if (strtoupper((string) $pm->attribute) === 'CHARSET') { $cs = strtoupper((string) $pm->value); break; }
    }
    if ($cs !== '' && $cs !== 'UTF-8' && $cs !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
        $conv = @mb_convert_encoding($txt, 'UTF-8', $cs);
        if ($conv !== false) { $txt = $conv; }
    }
    return $txt;
}

/** HTML-törzs egyszerű szöveggé alakítása (megjelenítéshez). */
function htmlToText(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
    $html = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $html) ?? $html;
    return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** A keresett altípusú (PLAIN/HTML) text-rész megkeresése rekurzívan, helyes rész-számmal. */
function findTextPart($mc, int $msgno, array $parts, string $want, string $prefix = ''): ?string
{
    foreach ($parts as $i => $p) {
        $no = $prefix === '' ? (string) ($i + 1) : $prefix . '.' . ($i + 1);
        $isText = (int) ($p->type ?? 0) === 0; // 0 = text
        $sub = strtoupper((string) ($p->subtype ?? ''));
        if ($isText && $sub === $want) {
            $raw = (string) @imap_fetchbody($mc, $msgno, $no);
            return partToUtf8(decodeBody($raw, (int) ($p->encoding ?? 0)), $p);
        }
        if (!empty($p->parts)) {
            $found = findTextPart($mc, $msgno, $p->parts, $want, $no);
            if ($found !== null) { return $found; }
        }
    }
    return null;
}

/** Az üzenet olvasható szöveges törzse (text/plain-t részesíti előnyben). */
function imapPlainBody($mc, int $msgno): string
{
    $structure = @imap_fetchstructure($mc, $msgno);
    if (!$structure) {
        return (string) @imap_body($mc, $msgno);
    }
    if (empty($structure->parts)) { // nem multipart
        $txt = partToUtf8(decodeBody((string) @imap_body($mc, $msgno), (int) ($structure->encoding ?? 0)), $structure);
        return strtoupper((string) ($structure->subtype ?? '')) === 'HTML' ? htmlToText($txt) : $txt;
    }
    $plain = findTextPart($mc, $msgno, $structure->parts, 'PLAIN');
    if ($plain !== null && trim($plain) !== '') { return $plain; }
    $html = findTextPart($mc, $msgno, $structure->parts, 'HTML');
    return $html !== null ? htmlToText($html) : '';
}

/** Fejléc-dátum barátságos formára (Y-m-d H:i), vagy az eredeti. */
function niceDate(string $d): string
{
    $t = strtotime($d);
    return $t ? date('Y-m-d H:i', $t) : h($d);
}

// ---------------------------------------------------------------------------

$cfg  = imapConfig();
$host = (string) ($cfg['host'] ?? '');
$port = (int) ($cfg['port'] ?? 993);
$user = (string) ($cfg['user'] ?? '');
$pass = (string) ($cfg['pass'] ?? '');

$hasExt     = function_exists('imap_open');
$configured = $host !== '' && $user !== '' && $pass !== '';

$error       = '';
$messages    = [];
$unreadCount = 0;
$openUid     = (int) ($_GET['uid'] ?? 0);
$openMsg     = null;

if ($hasExt && $configured) {
    // 1) Gyors elérhetőség-ellenőrzés, hogy rossz/elérhetetlen host NE fusson
    //    időtúllépésbe (ami 500-at okozna). Ha az fsockopen tiltott, kihagyjuk.
    $reachable = true; $reachErr = '';
    if (function_exists('fsockopen')) {
        $sock = @fsockopen($host, $port, $errno, $errstr, 6);
        if ($sock === false) { $reachable = false; $reachErr = (string) $errstr; }
        else { fclose($sock); }
    }

    if (!$reachable) {
        $error = 'Nem érhető el az IMAP-kiszolgáló (' . $host . ':' . $port . '). '
            . 'Ellenőrizd az IMAP_HOST / IMAP_PORT beállítást a Rackhost webmail adatai alapján.'
            . ($reachErr !== '' ? ' (' . $reachErr . ')' : '');
    } else {
        try {
            @imap_timeout(IMAP_OPENTIMEOUT, 10);
            @imap_timeout(IMAP_READTIMEOUT, 10);
            $mbox = '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}INBOX';
            $mc = @imap_open($mbox, $user, $pass, 0, 0);
            if ($mc === false) {
                $error = 'Nem sikerült belépni a postafiókba: ' . (string) imap_last_error()
                    . ' — ellenőrizd a felhasználónevet (IMAP_USER) és a jelszót (IMAP_PASSWORD).';
                @imap_errors();
            } else {
                $ids = @imap_sort($mc, SORTDATE, true) ?: []; // dátum szerint csökkenő (reverse=bool!)
                foreach (array_slice($ids, 0, 40) as $num) {
                    $ov = @imap_fetch_overview($mc, (string) $num, 0);
                    if (!$ov || !isset($ov[0])) { continue; }
                    $o = $ov[0];
                    $seen = !empty($o->seen);
                    if (!$seen) { $unreadCount++; }
                    $messages[] = [
                        'uid'     => (int) ($o->uid ?? 0),
                        'from'    => decodeMime((string) ($o->from ?? '')),
                        'subject' => decodeMime((string) ($o->subject ?? '')),
                        'date'    => (string) ($o->date ?? ''),
                        'seen'    => $seen,
                    ];
                }

                if ($openUid > 0) {
                    $msgno = @imap_msgno($mc, $openUid);
                    if ($msgno) {
                        $hi = @imap_headerinfo($mc, $msgno);
                        $openMsg = [
                            'from'    => decodeMime((string) ($hi->fromaddress ?? '')),
                            'to'      => decodeMime((string) ($hi->toaddress ?? '')),
                            'subject' => decodeMime((string) ($hi->subject ?? '')),
                            'date'    => (string) ($hi->date ?? ''),
                            'reply'   => extractEmail((string) ($hi->fromaddress ?? '')),
                            'body'    => imapPlainBody($mc, $msgno),
                        ];
                    } else {
                        $error = 'A megnyitni kívánt üzenet nem található.';
                    }
                }
                @imap_close($mc);
            }
            @imap_errors();
        } catch (Throwable $e) {
            // Bármilyen váratlan IMAP-hiba: 500 helyett érthető üzenet.
            error_log('beerkezo.php IMAP hiba: ' . $e->getMessage());
            $error = 'IMAP hiba történt: ' . $e->getMessage();
            @imap_errors();
        }
    }
}

$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Beérkező — admin — holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <?php require __DIR__ . '/partials/nav.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <h1>Beérkező e-mailek
        <?php if ($unreadCount > 0): ?><span class="mail-badge"><?= (int) $unreadCount ?> új</span><?php endif; ?>
      </h1>
      <a class="btn btn--primary" href="beerkezo.php">↻ Frissítés</a>
    </div>
    <p class="admin-lead"><?= h($user !== '' ? $user : 'info@holborozzak.hu') ?> postafiók — csak olvasás, a levelek a szerveren maradnak.</p>

    <?php if (!$hasExt): ?>
      <div class="admin-error">A PHP <code>imap</code> kiterjesztése nem elérhető ezen a kiszolgálón,
        ezért a beérkező leveleket nem tudom lekérni. Kérd a Rackhost támogatásától az
        <code>imap</code> PHP-kiterjesztés bekapcsolását, vagy használd a webmailt.</div>

    <?php elseif (!$configured): ?>
      <div class="admin-empty" style="text-align:left">
        <h2 class="admin-h2" style="margin-top:0">Beállítás szükséges</h2>
        <p>A beérkező levelek olvasásához add meg a postafiók IMAP-adatait GitHub repository
          <strong>secret</strong>ként (a jelszó soha nem kerül a kódba):</p>
        <ul class="admin-dedup-list" style="margin-left:1.2rem">
          <li><code>IMAP_PASSWORD</code> — az <code>info@holborozzak.hu</code> postafiók jelszava <em>(kötelező)</em></li>
          <li><code>IMAP_HOST</code> — IMAP-kiszolgáló (alap: <code>mail.rackhost.hu</code>, ellenőrizd a webmailban)</li>
          <li><code>IMAP_USER</code> — általában a teljes cím: <code>info@holborozzak.hu</code></li>
          <li><code>IMAP_PORT</code> — SSL-nél <code>993</code> (alap)</li>
        </ul>
        <p class="admin-sub">A secretek beállítása után egy új deploy (push a <code>main</code>-re) generálja
          a <code>config.php</code>-ba az <code>imap</code> szekciót, és a lap élesedik.</p>
      </div>

    <?php elseif ($error !== ''): ?>
      <div class="admin-error"><?= h($error) ?>
        <br><span class="admin-sub">Ellenőrizd az <code>IMAP_HOST</code>/<code>IMAP_PORT</code> és a jelszó helyességét.</span>
      </div>

    <?php elseif ($openMsg !== null): ?>
      <p><a class="admin-link" href="beerkezo.php">← Vissza a listához</a></p>
      <div class="mailview">
        <div class="mailview__head">
          <h2 class="mailview__subject"><?= h($openMsg['subject'] !== '' ? $openMsg['subject'] : '(nincs tárgy)') ?></h2>
          <div class="mailview__meta">
            <span><strong>Feladó:</strong> <?= h($openMsg['from']) ?></span>
            <span><strong>Címzett:</strong> <?= h($openMsg['to']) ?></span>
            <span><strong>Dátum:</strong> <?= niceDate($openMsg['date']) ?></span>
          </div>
          <?php if ($openMsg['reply'] !== ''): ?>
            <a class="btn btn--ghost" href="mailto:<?= h($openMsg['reply']) ?>?subject=<?= h(rawurlencode('Re: ' . $openMsg['subject'])) ?>">Válasz e-mailben →</a>
          <?php endif; ?>
        </div>
        <div class="mailbody"><?= nl2br(h($openMsg['body'] !== '' ? $openMsg['body'] : '(A levélnek nincs szöveges törzse.)')) ?></div>
      </div>

    <?php elseif (!$messages): ?>
      <div class="admin-empty">A postafiók üres, vagy nincs megjeleníthető levél.</div>

    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:2rem"></th>
            <th>Feladó</th>
            <th>Tárgy</th>
            <th class="admin-num">Dátum</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($messages as $m): ?>
            <tr class="<?= $m['seen'] ? '' : 'mail-row--unread' ?>">
              <td><?php if (!$m['seen']): ?><span class="mail-dot" title="Olvasatlan"></span><?php endif; ?></td>
              <td><a class="mail-link" href="beerkezo.php?uid=<?= (int) $m['uid'] ?>"><?= h($m['from'] !== '' ? $m['from'] : '(ismeretlen feladó)') ?></a></td>
              <td><a class="mail-link" href="beerkezo.php?uid=<?= (int) $m['uid'] ?>"><?= h($m['subject'] !== '' ? $m['subject'] : '(nincs tárgy)') ?></a></td>
              <td class="admin-num"><?= niceDate($m['date']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="admin-note">A legutóbbi <?= count($messages) ?> levél (dátum szerint csökkenő). Kattints egy sorra a megnyitáshoz.</p>
    <?php endif; ?>
  </main>
</body>
</html>
