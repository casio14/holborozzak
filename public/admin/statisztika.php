<?php
declare(strict_types=1);

// holborozzak.hu — admin: kattintás- és megtekintés-statisztika.
//
// Forrás: event_interactions (view / click_website / click_ticket).
// Egyedi látogató: a mérési sütit elfogadóknál az anonim session_id (napokon
// átívelően pontos), a többieknél a napi sóval hashelt IP a becslés (napok
// között nem összeköthető). A „Sütis látogató-mérés" blokk csak session-alapú.

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require_admin();

// Időszak-fülek (nap; 0 = teljes időszak)
$PERIODS = [7 => 'Utolsó 7 nap', 30 => 'Utolsó 30 nap', 90 => 'Utolsó 90 nap', 0 => 'Teljes időszak'];
$days = (int) ($_GET['nap'] ?? 30);
if (!isset($PERIODS[$days])) {
    $days = 30;
}
// A $days fix whitelist-ből ($PERIODS) jön, így biztonságos az interpoláció.
$where = $days > 0 ? "WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';

// Egyedi látogató: a sütis anonim azonosító a pontos; ahol nincs (nem járult
// hozzá), ott a napi sóval hashelt IP a becslés.
$UID = 'COALESCE(i.session_id, i.ip_hash)';
// Csak sütis (hozzájárult) látogatók — a napokon átívelő metrikákhoz
$whereSess = $where !== ''
    ? $where . ' AND i.session_id IS NOT NULL'
    : 'WHERE i.session_id IS NOT NULL';

$totals = ['view' => 0, 'click_website' => 0, 'click_ticket' => 0];
$uniq   = ['view' => 0, 'click_website' => 0, 'click_ticket' => 0];
$visitor = ['sessions' => 0, 'returning' => 0, 'viewers' => 0, 'clickers' => 0, 'avg_events' => 0.0];
$rows = [];
$daily = [];
$referrers = [];
$aiTotals = ['interactions' => 0, 'unique' => 0, 'clicks' => 0];
$aiRows = [];
$searchTotals = ['interactions' => 0, 'unique' => 0, 'clicks' => 0];
$searchRows = [];
$dbError = false;

// AI-ajánlások forrása: az ai_referrals tábla (a fő oldalak logAiReferral() hívása
// tölti — utm_source VAGY referrer alapján, a nyitóoldalt is beleértve).
try {
    $pdo = db();

    // Összesítők típusonként
    foreach ($pdo->query(
        "SELECT i.type, COUNT(*) AS c, COUNT(DISTINCT {$UID}) AS u
         FROM event_interactions i {$where} GROUP BY i.type"
    ) as $r) {
        $totals[$r['type']] = (int) $r['c'];
        $uniq[$r['type']]   = (int) $r['u'];
    }

    // Sütis látogató-metrikák (pontos, napokon átívelő számok)
    $v = $pdo->query(
        "SELECT COUNT(DISTINCT i.session_id) AS s,
                COUNT(DISTINCT CASE WHEN i.type = 'view' THEN i.session_id END) AS vw,
                COUNT(DISTINCT CASE WHEN i.type IN ('click_website','click_ticket') THEN i.session_id END) AS cl
         FROM event_interactions i {$whereSess}"
    )->fetch();
    if ($v) {
        $visitor['sessions'] = (int) $v['s'];
        $visitor['viewers']  = (int) $v['vw'];
        $visitor['clickers'] = (int) $v['cl'];
    }
    // Visszatérő: legalább 2 különböző napon aktív sütis látogató
    $visitor['returning'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT i.session_id FROM event_interactions i {$whereSess}
            GROUP BY i.session_id
            HAVING COUNT(DISTINCT DATE(i.created_at)) >= 2
         ) t"
    )->fetchColumn();
    // Átlagosan hány KÜLÖNBÖZŐ eseményt néz meg egy sütis látogató
    $visitor['avg_events'] = (float) ($pdo->query(
        "SELECT AVG(t.cnt) FROM (
            SELECT COUNT(DISTINCT i.event_id) AS cnt
            FROM event_interactions i {$whereSess} AND i.type = 'view'
            GROUP BY i.session_id
         ) t"
    )->fetchColumn() ?: 0);

    // Eseményenkénti bontás (kattintás szerint csökkenő)
    $rows = $pdo->query(
        "SELECT e.id, e.title, e.city, e.status, e.start_datetime,
                SUM(i.type = 'view')          AS views,
                COUNT(DISTINCT CASE WHEN i.type = 'view' THEN {$UID} END) AS uv,
                SUM(i.type = 'click_website') AS cw,
                SUM(i.type = 'click_ticket')  AS ct
         FROM event_interactions i
         JOIN events e ON e.id = i.event_id
         {$where}
         GROUP BY e.id
         ORDER BY (SUM(i.type = 'click_website') + SUM(i.type = 'click_ticket')) DESC,
                  views DESC
         LIMIT 200"
    )->fetchAll();

    // Napi bontás — az utolsó 14 nap trendje (az időszak-fültől független)
    $daily = $pdo->query(
        "SELECT DATE(created_at) AS d,
                SUM(type = 'view')          AS v,
                SUM(type = 'click_website') AS cw,
                SUM(type = 'click_ticket')  AS ct
         FROM event_interactions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
         GROUP BY DATE(created_at)
         ORDER BY d DESC"
    )->fetchAll();

    // Honnan jönnek a látogatók? Hivatkozó domainek (a saját oldal nélkül)
    $refCond = "i.referrer IS NOT NULL AND i.referrer NOT LIKE '%holborozzak.hu%'"
        . " AND i.referrer NOT LIKE '%kissptrk.hu%'";
    $whereRef = $where !== '' ? $where . ' AND ' . $refCond : 'WHERE ' . $refCond;
    $referrers = $pdo->query(
        "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(i.referrer, '/', 3), '//', -1) AS host,
                COUNT(*) AS c,
                COUNT(DISTINCT {$UID}) AS u
         FROM event_interactions i {$whereRef}
         GROUP BY host
         ORDER BY c DESC
         LIMIT 15"
    )->fetchAll();

    // AI-ajánlások: AI-asszisztensből érkező látogatók (ai_referrals — utm_source VAGY referrer).
    ensureAiReferralsTable($pdo);
    $whereAir = $days > 0 ? "WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';
    $at = $pdo->query(
        "SELECT COUNT(*) AS c, COUNT(DISTINCT COALESCE(a.session_id, a.ip_hash)) AS u
         FROM ai_referrals a {$whereAir}"
    )->fetch();
    if ($at) {
        $aiTotals['interactions'] = (int) $at['c'];
        $aiTotals['unique']       = (int) $at['u'];
    }
    // Továbbkattintás: AI-ból érkezett látogatók, akik utóbb a szervező oldalára kattintottak
    // (azonos látogató-azonosító alapján; a napi sózott ip_hash miatt főleg aznapi kattintás).
    $clkWhere = $days > 0 ? "AND i.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';
    $aiTotals['clicks'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM event_interactions i
         WHERE i.type IN ('click_website','click_ticket') {$clkWhere}
           AND COALESCE(i.session_id, i.ip_hash) IN (
             SELECT COALESCE(a.session_id, a.ip_hash) FROM ai_referrals a {$whereAir}
           )"
    )->fetchColumn();
    // Platformonkénti bontás
    $aiRows = $pdo->query(
        "SELECT a.source AS ai, COUNT(*) AS c,
                COUNT(DISTINCT COALESCE(a.session_id, a.ip_hash)) AS u
         FROM ai_referrals a {$whereAir}
         GROUP BY a.source
         ORDER BY c DESC"
    )->fetchAll();

    // Keresőforgalom: keresőmotorból (Google, Bing stb.) érkező látogatók (search_referrals — referrer).
    ensureSearchReferralsTable($pdo);
    $whereSr = $days > 0 ? "WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';
    $st = $pdo->query(
        "SELECT COUNT(*) AS c, COUNT(DISTINCT COALESCE(s.session_id, s.ip_hash)) AS u
         FROM search_referrals s {$whereSr}"
    )->fetch();
    if ($st) {
        $searchTotals['interactions'] = (int) $st['c'];
        $searchTotals['unique']       = (int) $st['u'];
    }
    // Továbbkattintás: keresőből érkezett látogatók, akik utóbb a szervező oldalára kattintottak.
    $searchTotals['clicks'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM event_interactions i
         WHERE i.type IN ('click_website','click_ticket') {$clkWhere}
           AND COALESCE(i.session_id, i.ip_hash) IN (
             SELECT COALESCE(s.session_id, s.ip_hash) FROM search_referrals s {$whereSr}
           )"
    )->fetchColumn();
    // Keresőnkénti bontás
    $searchRows = $pdo->query(
        "SELECT s.source AS se, COUNT(*) AS c,
                COUNT(DISTINCT COALESCE(s.session_id, s.ip_hash)) AS u
         FROM search_referrals s {$whereSr}
         GROUP BY s.source
         ORDER BY c DESC"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('admin statisztika DB hiba: ' . $e->getMessage());
    $dbError = true;
}

$clicksTotal = $totals['click_website'] + $totals['click_ticket'];

/** CTR (kattintás / megtekintés) szövegesen; 0 megtekintésnél kötőjel. */
function ctr(int $clicks, int $views): string
{
    return $views > 0 ? number_format($clicks / $views * 100, 1, ',', '') . '%' : '—';
}

$STATUS_PILL = ['draft' => 'Beérkezett', 'published' => 'Közzétett', 'cancelled' => 'Lemondott'];
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Statisztika — admin — holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span><a href="index.php">Események</a> &nbsp;·&nbsp; <a href="jeloltek.php">Jelöltek</a> &nbsp;·&nbsp; <a href="feliratkozok.php">Feliratkozók</a> &nbsp;·&nbsp; <a href="../" target="_blank">Oldal megtekintése ↗</a> &nbsp;·&nbsp; <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1>Statisztika</h1>

    <?php if ($dbError): ?>
      <div class="admin-error">Nem sikerült lekérdezni a statisztikát. Ellenőrizd, hogy a
        <code>001_add_analytics.sql</code> migráció le van-e futtatva az adatbázison.</div>
    <?php endif; ?>

    <nav class="admin-tabs">
      <?php foreach ($PERIODS as $d => $label): ?>
        <a class="admin-tab<?= $days === $d ? ' is-active' : '' ?>" href="statisztika.php?nap=<?= $d ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-stats">
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($totals['view'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Megtekintés</span>
        <span class="admin-stat__sub">~<?= number_format($uniq['view'], 0, ',', ' ') ?> egyedi</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($totals['click_website'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Honlap-kattintás</span>
        <span class="admin-stat__sub">~<?= number_format($uniq['click_website'], 0, ',', ' ') ?> egyedi</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($totals['click_ticket'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Jegy-kattintás</span>
        <span class="admin-stat__sub">~<?= number_format($uniq['click_ticket'], 0, ',', ' ') ?> egyedi</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= ctr($clicksTotal, $totals['view']) ?></span>
        <span class="admin-stat__label">CTR (kattintás / megtekintés)</span>
        <span class="admin-stat__sub"><?= number_format($clicksTotal, 0, ',', ' ') ?> kattintás összesen</span>
      </div>
    </div>

    <h2 class="admin-h2">🤖 AI-ajánlások <span class="admin-stat__sub">(látogatók, akik egy AI-asszisztens válaszából érkeztek)</span></h2>
    <div class="admin-stats">
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($aiTotals['interactions'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">AI-ból érkezés</span>
        <span class="admin-stat__sub">látogatás AI-forrásból (bármely oldalra)</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num">~<?= number_format($aiTotals['unique'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Egyedi AI-látogató</span>
        <span class="admin-stat__sub">különböző látogató AI-ajánlásból</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($aiTotals['clicks'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Továbbkattintás</span>
        <span class="admin-stat__sub">AI-látogatóból a szervező oldalára</span>
      </div>
    </div>
    <?php if ($aiRows): ?>
      <table class="admin-table admin-table--narrow">
        <thead>
          <tr>
            <th>AI-asszisztens</th>
            <th class="admin-num">Érkezés</th>
            <th class="admin-num">Egyedi látogató</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aiRows as $r): ?>
            <tr>
              <td><?= h($r['ai']) ?></td>
              <td class="admin-num"><?= number_format((int) $r['c'], 0, ',', ' ') ?></td>
              <td class="admin-num">~<?= number_format((int) $r['u'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="admin-empty">Ebben az időszakban még nem érkezett látogató azonosítható
        AI-asszisztensből. Ahogy a ChatGPT, Perplexity, Gemini stb. elkezdi ajánlani az
        oldalt és a felhasználók rákattintanak, itt fog megjelenni.</div>
    <?php endif; ?>
    <p class="admin-note">Ez azt méri, hányan <strong>érkeztek</strong> egy AI-asszisztens
      (ChatGPT, Perplexity, Google Gemini, Microsoft Copilot, Claude) válaszából az oldalra —
      a felismerés a link <code>?utm_source=…</code> paramétere VAGY a hivatkozó (referrer)
      alapján történik, <strong>bármely oldalra</strong> (a nyitóoldalt is beleértve). A
      „Továbbkattintás" azt mutatja, hányan léptek tovább a szervező oldalára. Amikor egy AI
      csak <em>megemlíti</em> az oldalt kattintás nélkül, az technikailag nem mérhető. (A Google
      AI Overviews a sima <code>google.com</code> hivatkozóként érkezik, ezért nem különíthető el.)</p>

    <h2 class="admin-h2">🔎 Keresőforgalom <span class="admin-stat__sub">(látogatók, akik egy keresőmotor találatából érkeztek)</span></h2>
    <div class="admin-stats">
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($searchTotals['interactions'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Keresőből érkezés</span>
        <span class="admin-stat__sub">látogatás keresőmotorból (bármely oldalra)</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num">~<?= number_format($searchTotals['unique'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Egyedi kereső-látogató</span>
        <span class="admin-stat__sub">különböző látogató keresőből</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($searchTotals['clicks'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Továbbkattintás</span>
        <span class="admin-stat__sub">kereső-látogatóból a szervező oldalára</span>
      </div>
    </div>
    <?php if ($searchRows): ?>
      <table class="admin-table admin-table--narrow">
        <thead>
          <tr>
            <th>Kereső</th>
            <th class="admin-num">Érkezés</th>
            <th class="admin-num">Egyedi látogató</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($searchRows as $r): ?>
            <tr>
              <td><?= h($r['se']) ?></td>
              <td class="admin-num"><?= number_format((int) $r['c'], 0, ',', ' ') ?></td>
              <td class="admin-num">~<?= number_format((int) $r['u'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="admin-empty">Ebben az időszakban még nem érkezett látogató azonosítható
        keresőmotorból. Ahogy az oldal feljebb kerül a Google/Bing találatok között és a
        felhasználók rákattintanak, itt fog megjelenni.</div>
    <?php endif; ?>
    <p class="admin-note">Ez azt méri, hányan <strong>érkeztek</strong> egy keresőmotor
      (Google, Bing, DuckDuckGo, Yahoo stb.) találatából az oldalra — a felismerés a hivatkozó
      (referrer) hostja alapján történik, <strong>bármely oldalra</strong> (a nyitóoldalt is
      beleértve). A „Továbbkattintás" azt mutatja, hányan léptek tovább a szervező oldalára. A
      keresők egy része adatvédelmi okból nem küld hivatkozót — az ilyen érkezés nem
      azonosítható, ezért a valós keresőforgalom ennél magasabb lehet. (A Google AI Overviews is
      sima <code>google.com</code> hivatkozóként érkezik, így itt „Google"-ként számolódik.)</p>

    <h2 class="admin-h2">Sütis látogató-mérés <span class="admin-stat__sub">(csak a mérési sütit elfogadó látogatók — napokon átívelően pontos)</span></h2>
    <div class="admin-stats">
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($visitor['sessions'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Mért látogató</span>
        <span class="admin-stat__sub">egyedi böngésző, duplaszámolás nélkül</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($visitor['returning'], 0, ',', ' ') ?></span>
        <span class="admin-stat__label">Visszatérő látogató</span>
        <span class="admin-stat__sub">legalább 2 különböző napon járt itt</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= ctr($visitor['clickers'], $visitor['viewers']) ?></span>
        <span class="admin-stat__label">Látogató-konverzió</span>
        <span class="admin-stat__sub"><?= number_format($visitor['clickers'], 0, ',', ' ') ?> kattintó / <?= number_format($visitor['viewers'], 0, ',', ' ') ?> megtekintő</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__num"><?= number_format($visitor['avg_events'], 1, ',', ' ') ?></span>
        <span class="admin-stat__label">Esemény / látogató</span>
        <span class="admin-stat__sub">átlagosan ennyi különböző eseményt néz meg</span>
      </div>
    </div>

    <h2 class="admin-h2">Események szerint</h2>
    <?php if (!$rows): ?>
      <div class="admin-empty">Ebben az időszakban még nincs rögzített interakció.</div>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Esemény</th>
            <th class="admin-num">Megtekintés</th>
            <th class="admin-num">Egyedi látogató</th>
            <th class="admin-num">Honlap katt.</th>
            <th class="admin-num">Jegy katt.</th>
            <th class="admin-num">CTR</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $views = (int) $r['views']; $uv = (int) $r['uv'];
              $cw = (int) $r['cw']; $ct = (int) $r['ct']; ?>
            <tr>
              <td>
                <strong><?= h($r['title']) ?></strong>
                <?php if (($r['status'] ?? '') !== 'published'): ?>
                  <span class="admin-pill admin-pill--draft"><?= h($STATUS_PILL[$r['status']] ?? $r['status']) ?></span>
                <?php endif; ?>
                <br><span class="admin-stat__sub"><?= h(trim(($r['city'] ? $r['city'] . ' · ' : '') . formatDateRange($r['start_datetime'], null))) ?>
                  &nbsp;·&nbsp; <a class="admin-link" href="esemeny-preview.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener">Előnézet ↗</a></span>
              </td>
              <td class="admin-num"><?= number_format($views, 0, ',', ' ') ?></td>
              <td class="admin-num">~<?= number_format($uv, 0, ',', ' ') ?></td>
              <td class="admin-num"><?= number_format($cw, 0, ',', ' ') ?></td>
              <td class="admin-num"><?= number_format($ct, 0, ',', ' ') ?></td>
              <td class="admin-num"><?= ctr($cw + $ct, $views) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 class="admin-h2">Napi bontás (utolsó 14 nap)</h2>
    <?php if (!$daily): ?>
      <div class="admin-empty">Az elmúlt 14 napban még nincs rögzített interakció.</div>
    <?php else: ?>
      <table class="admin-table admin-table--narrow">
        <thead>
          <tr>
            <th>Nap</th>
            <th class="admin-num">Megtekintés</th>
            <th class="admin-num">Honlap katt.</th>
            <th class="admin-num">Jegy katt.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daily as $r): ?>
            <tr>
              <td><?= h($r['d']) ?></td>
              <td class="admin-num"><?= number_format((int) $r['v'], 0, ',', ' ') ?></td>
              <td class="admin-num"><?= number_format((int) $r['cw'], 0, ',', ' ') ?></td>
              <td class="admin-num"><?= number_format((int) $r['ct'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 class="admin-h2">Honnan jönnek a látogatók? <span class="admin-stat__sub">(hivatkozó domainek, a saját oldal nélkül)</span></h2>
    <?php if (!$referrers): ?>
      <div class="admin-empty">Ebben az időszakban nincs külső hivatkozásból érkező interakció.</div>
    <?php else: ?>
      <table class="admin-table admin-table--narrow">
        <thead>
          <tr>
            <th>Domain</th>
            <th class="admin-num">Interakció</th>
            <th class="admin-num">Egyedi látogató</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($referrers as $r): ?>
            <tr>
              <td><?= h($r['host']) ?></td>
              <td class="admin-num"><?= number_format((int) $r['c'], 0, ',', ' ') ?></td>
              <td class="admin-num">~<?= number_format((int) $r['u'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <p class="admin-note">Botok nélkül számolva. Az „egyedi" (~) értékeknél a mérési sütit
      elfogadó látogatóknál a süti anonim azonosítója számít (napokon átívelően pontos);
      a többieknél a napi sóval hashelt IP a becslés (napok között nem összeköthető, ezért
      több napos időszakon a napi egyediek összege). A „Sütis látogató-mérés" blokk csak a
      sütit elfogadókat tartalmazza. Az impresszió-mérés (lista-megjelenések) még nincs bekötve.</p>
  </main>
</body>
</html>
