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
$pv = ['opens' => 0, 'home' => 0, 'unique' => 0, 'cookie' => 0, 'nocookie' => 0];
$pvDaily = [];
// A napi látogató-diagram ablaka az időszak-fület követi (teljes időszaknál 90 nap).
$pvDays = $days > 0 ? $days : 90;
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

    // Honlap-látogatottság (page_views): összes oldalmegnyitás + sütis/süti nélküli bontás
    ensurePageViewsTable($pdo);
    $wherePv = $days > 0 ? "WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';
    $pvr = $pdo->query(
        "SELECT COUNT(*) AS opens,
                SUM(path = '/' OR path = '') AS home,
                COUNT(DISTINCT COALESCE(session_id, ip_hash)) AS uniq,
                COUNT(DISTINCT session_id) AS ck,
                COUNT(DISTINCT CASE WHEN session_id IS NULL THEN ip_hash END) AS nock
         FROM page_views {$wherePv}"
    )->fetch();
    if ($pvr) {
        $pv = [
            'opens'    => (int) $pvr['opens'],
            'home'     => (int) $pvr['home'],
            'unique'   => (int) $pvr['uniq'],
            'cookie'   => (int) $pvr['ck'],
            'nocookie' => (int) $pvr['nock'],
        ];
    }

    // Napi látogatottság: naponta hány KÜLÖNBÖZŐ látogató járt az oldalon.
    // A napi sózott ip_hash napon belül pontosan dedupál, így a napi egyedi
    // szám a sütit el nem fogadóknál is megbízható.
    $pvDaily = $pdo->query(
        "SELECT DATE(created_at) AS d,
                COUNT(*) AS opens,
                COUNT(DISTINCT COALESCE(session_id, ip_hash)) AS uniq
         FROM page_views
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($pvDays - 1) . " DAY)
         GROUP BY DATE(created_at)
         ORDER BY d"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('admin statisztika DB hiba: ' . $e->getMessage());
    $dbError = true;
}

$clicksTotal = $totals['click_website'] + $totals['click_ticket'];

// --- Diagram-adatok előkészítése ---
// Napi sorozat: 14 nap kronológikus sorrendben, a hiányzó napok 0-val feltöltve.
$dailyMap = [];
foreach ($daily as $r) {
    $dailyMap[$r['d']] = $r;
}
$dailyCats = $dailyDates = $sViews = $sCw = $sCt = [];
$today0 = new DateTimeImmutable('today');
for ($i = 13; $i >= 0; $i--) {
    $d = $today0->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    $row = $dailyMap[$d] ?? null;
    $dailyCats[]  = (int) substr($d, 8, 2);
    $dailyDates[] = substr($d, 5); // MM-DD (tooltip)
    $sViews[] = $row ? (int) $row['v'] : 0;
    $sCw[]    = $row ? (int) $row['cw'] : 0;
    $sCt[]    = $row ? (int) $row['ct'] : 0;
}
$dailyHasData = (array_sum($sViews) + array_sum($sCw) + array_sum($sCt)) > 0;

// Napi látogató-sorozat: az időszak minden napja kronológikusan, hiányzók 0-val.
$pvMap = [];
foreach ($pvDaily as $r) {
    $pvMap[$r['d']] = $r;
}
$pvCats = $pvDates = $sPvUniq = [];
for ($i = $pvDays - 1; $i >= 0; $i--) {
    $d = $today0->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    $row = $pvMap[$d] ?? null;
    // Hónapot átívelő idősor → "hó.nap" felirat (pl. 7.10)
    $pvCats[]  = (int) substr($d, 5, 2) . '.' . (int) substr($d, 8, 2) . '.';
    $pvDates[] = substr($d, 5); // MM-DD (tooltip)
    $sPvUniq[] = $row ? (int) $row['uniq'] : 0;
}
$pvHasData = array_sum($sPvUniq) > 0;

// Legnézettebb események (a sáv-diagramhoz): top 8 megtekintés szerint.
$rowsByViews = $rows;
usort($rowsByViews, static fn($a, $b) => (int) $b['views'] <=> (int) $a['views']);
$rowsByViews = array_slice($rowsByViews, 0, 8);
$maxViews = $rowsByViews ? max(array_map(static fn($r) => (int) $r['views'], $rowsByViews)) : 0;

// Forgalom-források (egy rangsorba: AI + keresők + hivatkozó domainek).
$sources = [];
if ($aiTotals['interactions'] > 0) {
    $sources[] = ['label' => 'AI-asszisztensek', 'value' => (int) $aiTotals['interactions']];
}
if ($searchTotals['interactions'] > 0) {
    $sources[] = ['label' => 'Keresők', 'value' => (int) $searchTotals['interactions']];
}
foreach ($referrers as $r) {
    $sources[] = ['label' => (string) $r['host'], 'value' => (int) $r['c']];
}
usort($sources, static fn($a, $b) => $b['value'] <=> $a['value']);
$sources = array_slice($sources, 0, 10);
$maxSource = $sources ? max(array_map(static fn($s) => $s['value'], $sources)) : 0;

/** CTR (kattintás / megtekintés) szövegesen; 0 megtekintésnél kötőjel. */
function ctr(int $clicks, int $views): string
{
    return $views > 0 ? number_format($clicks / $views * 100, 1, ',', '') . '%' : '—';
}

/** „Szép" felső határ a diagram tengelyéhez. */
function niceMax(int $m): int
{
    if ($m <= 0) {
        return 1;
    }
    $base = 10 ** (int) floor(log10(max($m, 1)));
    foreach ([1, 2, 2.5, 5, 10] as $mult) {
        if ($mult * $base >= $m) {
            return (int) ceil($mult * $base);
        }
    }
    return (int) (10 * $base);
}

/**
 * Napi oszlopdiagram inline SVG-ként (1–2 sorozat, EGY tengely — nincs kettős skála).
 * $cats: nap-számok; $dates: teljes dátum a tooltiphez;
 * $series: [['label'=>…, 'color'=>…, 'values'=>[int,…]], …].
 */
function renderDailyChart(array $cats, array $dates, array $series): string
{
    $W = 680; $H = 190; $padL = 30; $padR = 8; $padT = 12; $padB = 20;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $baseY = $padT + $plotH;
    $n = count($cats);
    if ($n === 0) {
        return '';
    }
    $maxRaw = 0;
    foreach ($series as $s) {
        foreach ($s['values'] as $v) { $maxRaw = max($maxRaw, (int) $v); }
    }
    $max = niceMax($maxRaw);
    $ns = count($series);
    $groupW = $plotW / $n;
    $inner  = $groupW * 0.76;
    $barW   = max(3.0, ($inner - ($ns - 1) * 2) / $ns);
    $rx = min(2.5, $barW / 2);
    // Hosszú idősornál (30-90 nap) csak minden k. nap kap tengelyfeliratot,
    // úgy, hogy a legutolsó (mai) nap mindig feliratozott legyen.
    $labelStep = max(1, (int) ceil($n / 16));
    $muted = '#9a8b7c'; $grid = '#e7ddcb';

    $svg = '<svg class="chart__svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Napi oszlopdiagram">';
    foreach ([0.0, 0.5, 1.0] as $f) {
        $y = round($baseY - $f * $plotH, 1);
        $svg .= '<line x1="' . $padL . '" y1="' . $y . '" x2="' . ($W - $padR) . '" y2="' . $y . '" stroke="' . $grid . '" stroke-width="1"/>';
        $svg .= '<text x="' . ($padL - 4) . '" y="' . ($y + 3) . '" text-anchor="end" font-size="9" fill="' . $muted . '">' . (int) round($f * $max) . '</text>';
    }
    for ($g = 0; $g < $n; $g++) {
        $gx0 = $padL + $g * $groupW + ($groupW - $inner) / 2;
        $cx  = $padL + $g * $groupW + $groupW / 2;
        if (($n - 1 - $g) % $labelStep === 0) {
            $svg .= '<text x="' . round($cx, 1) . '" y="' . ($baseY + 13) . '" text-anchor="middle" font-size="9" fill="' . $muted . '">' . h((string) $cats[$g]) . '</text>';
        }
        foreach ($series as $k => $s) {
            $v = (int) ($s['values'][$g] ?? 0);
            if ($v <= 0) {
                continue;
            }
            $bh = max(1.0, ($v / $max) * $plotH);
            $x  = round($gx0 + $k * ($barW + 2), 1);
            $y  = round($baseY - $bh, 1);
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . round($barW, 1) . '" height="' . round($bh, 1)
                . '" rx="' . round($rx, 1) . '" fill="' . $s['color'] . '"><title>' . h($dates[$g] . ' · ' . $s['label'] . ': ' . $v) . '</title></rect>';
        }
    }
    $svg .= '<line x1="' . $padL . '" y1="' . $baseY . '" x2="' . ($W - $padR) . '" y2="' . $baseY . '" stroke="#c9bba9" stroke-width="1"/>';
    $svg .= '</svg>';
    return $svg;
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
  <?php require __DIR__ . '/partials/nav.php'; ?>

  <main class="admin-main">
    <h1>Statisztika</h1>

    <?php if ($dbError): ?>
      <div class="admin-error">Nem sikerült lekérdezni a statisztikát. Ellenőrizd, hogy a
        <code>001_add_analytics.sql</code> migráció le van-e futtatva az adatbázison.</div>
    <?php endif; ?>

    <nav class="admin-tabs">
      <?php foreach ($PERIODS as $d => $label): ?>
        <a class="admin-tab period-tab<?= $days === $d ? ' is-active' : '' ?>" href="statisztika.php?nap=<?= $d ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="stat-tabs" role="tablist" aria-label="Statisztika nézetek">
      <button type="button" class="stat-tab" data-tab="attekintes" role="tab">Áttekintés</button>
      <button type="button" class="stat-tab" data-tab="esemenyek" role="tab">Események</button>
      <button type="button" class="stat-tab" data-tab="forgalom" role="tab">Forgalom &amp; források</button>
      <button type="button" class="stat-tab" data-tab="latogatok" role="tab">Látogatók</button>
    </div>

    <!-- ===== ÁTTEKINTÉS ===== -->
    <section class="stat-panel" id="tab-attekintes">
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

      <?php if ($dailyHasData): ?>
        <div class="chart-card">
          <h3 class="chart-card__title">Napi megtekintések</h3>
          <p class="chart-card__sub">Utolsó 14 nap — vidd az egeret egy oszlop fölé a részletekért</p>
          <div class="chart__wrap"><?= renderDailyChart($dailyCats, $dailyDates, [['label' => 'Megtekintés', 'color' => '#722f37', 'values' => $sViews]]) ?></div>
        </div>
        <div class="chart-card">
          <h3 class="chart-card__title">Napi kattintások</h3>
          <p class="chart-card__sub">Utolsó 14 nap — kimenő kattintások a szervezők oldalára</p>
          <div class="chart-legend">
            <span><i style="background:#b23a4a"></i> Honlap</span>
            <span><i style="background:#b5892f"></i> Jegy</span>
          </div>
          <div class="chart__wrap"><?= renderDailyChart($dailyCats, $dailyDates, [['label' => 'Honlap', 'color' => '#b23a4a', 'values' => $sCw], ['label' => 'Jegy', 'color' => '#b5892f', 'values' => $sCt]]) ?></div>
        </div>
      <?php else: ?>
        <div class="admin-empty">Az elmúlt 14 napban még nincs rögzített interakció a grafikonokhoz.</div>
      <?php endif; ?>

      <h2 class="admin-h2">Napi bontás <span class="admin-stat__sub">(táblázatos nézet — utolsó 14 nap)</span></h2>
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
    </section>

    <!-- ===== ESEMÉNYEK ===== -->
    <section class="stat-panel" id="tab-esemenyek">
      <?php if ($rowsByViews): ?>
        <div class="chart-card">
          <h3 class="chart-card__title">Legnézettebb események</h3>
          <p class="chart-card__sub">Top <?= count($rowsByViews) ?> megtekintés szerint</p>
          <div class="barlist">
            <?php foreach ($rowsByViews as $r): $v = (int) $r['views']; $pct = $maxViews > 0 ? round($v / $maxViews * 100) : 0; ?>
              <div class="barlist__row">
                <div class="barlist__label"><span><?= h($r['title']) ?></span><b><?= number_format($v, 0, ',', ' ') ?></b></div>
                <div class="barlist__track"><div class="barlist__fill" style="width:<?= $pct ?>%;background:#722f37"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <h2 class="admin-h2">Események szerint <span class="admin-stat__sub">(részletes táblázat)</span></h2>
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
    </section>

    <!-- ===== FORGALOM & FORRÁSOK ===== -->
    <section class="stat-panel" id="tab-forgalom">
      <?php if ($sources): ?>
        <div class="chart-card">
          <h3 class="chart-card__title">Forgalom források</h3>
          <p class="chart-card__sub">Honnan érkeznek a látogatók — AI-asszisztensek, keresők és hivatkozó domainek egy rangsorban</p>
          <div class="barlist">
            <?php foreach ($sources as $s): $pct = $maxSource > 0 ? round($s['value'] / $maxSource * 100) : 0; ?>
              <div class="barlist__row">
                <div class="barlist__label"><span><?= h($s['label']) ?></span><b><?= number_format($s['value'], 0, ',', ' ') ?></b></div>
                <div class="barlist__track"><div class="barlist__fill" style="width:<?= $pct ?>%;background:#b5892f"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-empty">Ebben az időszakban még nincs azonosítható külső forgalmi forrás.</div>
      <?php endif; ?>

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

      <h2 class="admin-h2">Hivatkozó domainek <span class="admin-stat__sub">(a saját oldal nélkül)</span></h2>
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
    </section>

    <!-- ===== LÁTOGATÓK ===== -->
    <section class="stat-panel" id="tab-latogatok">
      <h2 class="admin-h2">🏠 Honlap-látogatottság <span class="admin-stat__sub">(összes oldalmegnyitás — botok és a saját forgalmad nélkül)</span></h2>
      <div class="admin-stats">
        <div class="admin-stat">
          <span class="admin-stat__num"><?= number_format($pv['opens'], 0, ',', ' ') ?></span>
          <span class="admin-stat__label">Oldalmegnyitás</span>
          <span class="admin-stat__sub">ebből főoldal: <?= number_format($pv['home'], 0, ',', ' ') ?></span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat__num">~<?= number_format($pv['unique'], 0, ',', ' ') ?></span>
          <span class="admin-stat__label">Egyedi látogató</span>
          <span class="admin-stat__sub">különböző böngésző (becslés)</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat__num"><?= number_format($pv['cookie'], 0, ',', ' ') ?></span>
          <span class="admin-stat__label">Sütivel mért</span>
          <span class="admin-stat__sub">elfogadta a mérési sütit</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat__num">~<?= number_format($pv['nocookie'], 0, ',', ' ') ?></span>
          <span class="admin-stat__label">Süti nélkül</span>
          <span class="admin-stat__sub">nem fogadta el (IP-becslés)</span>
        </div>
      </div>
      <?php if ($pvHasData): ?>
        <div class="chart-card">
          <h3 class="chart-card__title">Napi látogatók</h3>
          <p class="chart-card__sub">Utolsó <?= $pvDays ?> nap — naponta ennyi különböző látogató járt az
            oldalon (botok és a saját forgalmad nélkül); vidd az egeret egy oszlop fölé a pontos számért</p>
          <div class="chart__wrap"><?= renderDailyChart($pvCats, $pvDates, [['label' => 'Látogató', 'color' => '#722f37', 'values' => $sPvUniq]]) ?></div>
        </div>
      <?php else: ?>
        <div class="admin-empty">Ebben az időszakban még nincs rögzített oldalmegnyitás a napi diagramhoz.</div>
      <?php endif; ?>

      <?php if ($pvHasData): ?>
        <h2 class="admin-h2">Napi látogatottság <span class="admin-stat__sub">(táblázatos nézet — utolsó 14 nap)</span></h2>
        <table class="admin-table admin-table--narrow">
          <thead>
            <tr>
              <th>Nap</th>
              <th class="admin-num">Látogató</th>
              <th class="admin-num">Oldalmegnyitás</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $pvTable = array_slice($pvDaily, -14);
            foreach (array_reverse($pvTable) as $r): ?>
              <tr>
                <td><?= h($r['d']) ?></td>
                <td class="admin-num"><?= number_format((int) $r['uniq'], 0, ',', ' ') ?></td>
                <td class="admin-num"><?= number_format((int) $r['opens'], 0, ',', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <p class="admin-note">Ez azt méri, <strong>hányszor nyitották meg a honlapot</strong> (bármely aloldalt),
        a botokat és a saját (admin) forgalmadat kihagyva. A „Sütivel mért" pontos, napokon átívelő; a
        „Süti nélkül" a napi sóval hashelt IP alapján becslés (több napos időszakon a napi egyediek összege).
        A napi diagram <strong>napon belül</strong> mindenkit pontosan dedupál (a napi sózott IP-hash miatt),
        így a napi látogatószám a sütit el nem fogadóknál is megbízható.</p>

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
      <p class="admin-note">Botok nélkül számolva. Az „egyedi" (~) értékeknél a mérési sütit
        elfogadó látogatóknál a süti anonim azonosítója számít (napokon átívelően pontos);
        a többieknél a napi sóval hashelt IP a becslés (napok között nem összeköthető, ezért
        több napos időszakon a napi egyediek összege). A „Sütis látogató-mérés" blokk csak a
        sütit elfogadókat tartalmazza. Az impresszió-mérés (lista-megjelenések) még nincs bekötve.</p>
    </section>

    <script>
      (function () {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('.stat-tab'));
        var panels = document.querySelectorAll('.stat-panel');
        if (!tabs.length) { return; }
        function activate(id) {
          tabs.forEach(function (t) {
            var on = t.dataset.tab === id;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          panels.forEach(function (p) { p.hidden = (p.id !== 'tab-' + id); });
        }
        tabs.forEach(function (t) {
          t.addEventListener('click', function () {
            activate(t.dataset.tab);
            history.replaceState(null, '', '#' + t.dataset.tab);
          });
        });
        var ids = tabs.map(function (t) { return t.dataset.tab; });
        var init = (location.hash || '').replace('#', '');
        activate(ids.indexOf(init) >= 0 ? init : ids[0]);
        // Az időszak-váltás őrizze meg az aktív fület (hash hozzáfűzése a linkhez).
        document.querySelectorAll('.period-tab').forEach(function (a) {
          a.addEventListener('click', function () {
            if (location.hash) { a.href = a.href.split('#')[0] + location.hash; }
          });
        });
      })();
    </script>
  </main>
</body>
</html>
