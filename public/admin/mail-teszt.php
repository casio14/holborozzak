<?php
declare(strict_types=1);

// holborozzak.hu — admin: e-mail küldés diagnosztika.
//
// Megmutatja, elérhető-e a PHP mail() a szerveren, mi a tényleges feladó cím
// (config 'mail' szekció / MAIL_FROM secret), és élesben kiküld egy tesztlevelet
// a megadott címre — a sendMailHtml() ugyanazon az úton, mint az üdvözlő levél.
// Így elkülöníthető, hogy a mail() bukik-e (false), vagy kimegy, de spambe kerül.

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';   // h()
require __DIR__ . '/../lib/mail.php';
require_admin();

// A config 'mail' szekciója — megjelenítéshez (belépett adminnak látható).
$mailCfg = [];
$cfgFile = __DIR__ . '/../config.php';
if (is_file($cfgFile)) {
    $all = require $cfgFile;
    $mailCfg = (array) ($all['mail'] ?? []);
}
$fromCfg = (string) ($mailCfg['from_email'] ?? '');
$fromEffective = $fromCfg !== ''
    ? $fromCfg
    : ('no-reply@' . preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu')));
$fromName = (string) ($mailCfg['from_name'] ?? 'holborozzak.hu');

// mail() elérhetőség
$mailExists = function_exists('mail');
$disabledFns = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$mailDisabled = in_array('mail', $disabledFns, true);
$sendmailPath = (string) ini_get('sendmail_path');

$result = null; // ['sent'=>bool,'to'=>string] | ['error'=>string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $result = ['error' => 'Érvénytelen CSRF token — töltsd újra az oldalt és próbáld újra.'];
    } else {
        $to = trim((string) ($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $result = ['error' => 'Adj meg egy érvényes e-mail címet.'];
        } else {
            $now = date('Y-m-d H:i:s');
            $html = '<p>Ez egy <strong>teszt e-mail</strong> a holborozzak.hu-ról.</p>'
                . '<p>Ha ezt látod, a szerver <code>mail()</code> küldése működik, és a '
                . 'kézbesítés is megtörtént. Küldés ideje: ' . h($now) . '.</p>'
                . '<p>🍷 holborozzak.hu</p>';
            $ret = sendMailHtml($to, 'holborozzak.hu — teszt e-mail 🍷', $html);
            $result = ['sent' => $ret, 'to' => $to];
        }
    }
}

$csrf = admin_csrf_token();
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>E-mail teszt — admin — holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span><a href="index.php">Események</a> &nbsp;·&nbsp; <a href="jeloltek.php">Jelöltek</a> &nbsp;·&nbsp; <a href="feliratkozok.php">Feliratkozók</a> &nbsp;·&nbsp; <a href="statisztika.php">Statisztika</a> &nbsp;·&nbsp; <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1>E-mail küldés teszt</h1>

    <?php if ($result !== null && isset($result['error'])): ?>
      <div class="admin-error"><?= h($result['error']) ?></div>
    <?php elseif ($result !== null && !empty($result['sent'])): ?>
      <div class="admin-error" style="background:#e7f4e4;color:#1f5c2e;border-color:#8bc196;">
        A <code>mail()</code> <strong>sikert jelzett (true)</strong> a(z) <?= h($result['to']) ?> címre.
        Ez azt jelenti, hogy a szerver átadta a levelet a levelezőnek. Ha nem érkezik meg,
        nézd meg a <strong>Spam / Promóciók</strong> mappát — akkor a probléma a kézbesítésnél
        (szűrés), nem a küldésnél van.
      </div>
    <?php elseif ($result !== null && isset($result['sent']) && $result['sent'] === false): ?>
      <div class="admin-error">
        A <code>mail()</code> <strong>hibát jelzett (false)</strong> a(z) <?= h($result['to']) ?> címre.
        A szerver el sem tudta küldeni a levelet — a probléma a küldésnél van (szerver mail-beállítás),
        nem a spam-szűrésnél.
      </div>
    <?php endif; ?>

    <h2 style="margin-top:1.4rem;">Szerver-állapot</h2>
    <table class="admin-table" style="max-width:640px;">
      <tr><td><code>mail()</code> elérhető</td><td><?= $mailExists ? '✅ igen' : '❌ nem' ?></td></tr>
      <tr><td><code>mail()</code> letiltva (<code>disable_functions</code>)</td><td><?= $mailDisabled ? '❌ IGEN — ez a hiba oka' : '✅ nem' ?></td></tr>
      <tr><td><code>sendmail_path</code></td><td><?= $sendmailPath !== '' ? h($sendmailPath) : '<em>(nincs beállítva)</em>' ?></td></tr>
      <tr><td>Feladó (tényleges)</td><td><?= h($fromEffective) ?><?= $fromCfg === '' ? ' <em>(fallback — a MAIL_FROM secret üres)</em>' : '' ?></td></tr>
      <tr><td>Feladó neve</td><td><?= h($fromName) ?></td></tr>
    </table>

    <h2 style="margin-top:1.4rem;">Tesztlevél küldése</h2>
    <p style="max-width:640px;color:var(--ink-600,#555);">Ír egy tesztlevelet a megadott címre a
      valódi küldő úton (<code>sendMailHtml</code>). Utána nézd meg a postafiókot <strong>és a spam mappát is</strong>.</p>
    <form method="post" action="mail-teszt.php" class="news-form" style="max-width:640px;justify-content:flex-start;">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="email" name="to" required placeholder="cimzett@example.com" aria-label="Címzett e-mail cím" style="min-width:280px;">
      <button type="submit" class="btn btn--primary">Tesztlevél küldése</button>
    </form>
  </main>
</body>
</html>
