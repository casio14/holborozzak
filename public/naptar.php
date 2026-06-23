<?php
declare(strict_types=1);

// holborozzak.hu — Eseménynaptár: havi naptárrács, dátum szerinti böngészés, szűrőkkel.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$now = new DateTimeImmutable('now');
$year  = (int) ($_GET['ev'] ?? $now->format('Y'));
$month = (int) ($_GET['ho'] ?? $now->format('n'));
if ($month < 1 || $month > 12) { $month = (int) $now->format('n'); }
if ($year < 2000 || $year > 2100) { $year = (int) $now->format('Y'); }

$first       = $now->setDate($year, $month, 1)->setTime(0, 0, 0);
$daysInMonth = (int) $first->format('t');
$monthStart  = $first;
$monthEnd    = $first->setDate($year, $month, $daysInMonth)->setTime(23, 59, 59);
$prev        = $first->modify('-1 month');
$next        = $first->modify('+1 month');
$monthTitle  = $year . '. ' . HU_MONTHS[$month];

// A hónap eseményei
$monthEvents = [];
try {
    $monthEvents = fetchEventsBetween(db(), $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'));
} catch (Throwable $e) {
    error_log('naptar.php DB hiba: ' . $e->getMessage());
}

// Szűrők (borvidék / kategória) — a hónap eseményeiből
$regionFilters = array_values(array_filter(array_map('strval', (array) ($_GET['borvidek'] ?? [])), 'strlen'));
$catFilters    = array_values(array_filter(array_map('strval', (array) ($_GET['kategoria'] ?? [])), 'strlen'));
$regionOptions = [];
$catOptions = [];
foreach ($monthEvents as $e) {
    if (!empty($e['region_slug'])) { $regionOptions[$e['region_slug']] = $e['region_name']; }
    foreach ($e['categories'] as $c) { $catOptions[$c['slug']] = $c['name']; }
}
asort($regionOptions);
asort($catOptions);
$hasFacets = (!empty($regionFilters) || !empty($catFilters));

$shown = applyFacets($monthEvents, $regionFilters, $catFilters);
$eventCount = count($shown);

// Hónap-lépegető URL-ek (a szűrők megőrzésével)
$facet = [];
if ($regionFilters) { $facet['borvidek'] = $regionFilters; }
if ($catFilters)    { $facet['kategoria'] = $catFilters; }
$facetQs  = $facet ? ('&' . http_build_query($facet)) : '';
$prevUrl  = 'naptar.php?ev=' . $prev->format('Y') . '&ho=' . $prev->format('n') . $facetQs;
$nextUrl  = 'naptar.php?ev=' . $next->format('Y') . '&ho=' . $next->format('n') . $facetQs;
$todayUrl = 'naptar.php' . ($facet ? ('?' . http_build_query($facet)) : '');

// Napra bontás (a szűrt eseményekből)
$daysEvents = array_fill(1, $daysInMonth, []);
foreach ($shown as $e) {
    $s  = new DateTimeImmutable($e['start_datetime']);
    $en = !empty($e['end_datetime']) ? new DateTimeImmutable($e['end_datetime']) : $s;
    $startDay = ($s  < $monthStart) ? 1 : (int) $s->format('j');
    $endDay   = ($en > $monthEnd)   ? $daysInMonth : (int) $en->format('j');
    for ($d = max(1, $startDay); $d <= min($daysInMonth, $endDay); $d++) {
        $daysEvents[$d][] = $e;
    }
}

$pageTitle = "Eseménynaptár — {$monthTitle} | holborozzak.hu";
$pageDescription = "Borrendezvények naptára ({$monthTitle}): nézd meg, mely napokon vannak "
    . "borfesztiválok, bornapok és kóstolók Magyarországon.";
$activeNav = 'naptar';

$ld = eventsItemListJsonLd($shown, $base, $dir, "Borrendezvények — {$monthTitle}");
if ($ld) {
    $jsonLd = $ld;
}

require __DIR__ . '/partials/header.php';
?>
  <div class="container container--wide">

    <div class="page-head">
      <h1>Eseménynaptár</h1>
      <p class="page-head__sub">Böngészd Magyarország borrendezvényeit dátum szerint, hónapról hónapra.</p>
    </div>

    <form class="facets" method="get" action="naptar.php" aria-label="Naptár szűrők">
      <input type="hidden" name="ev" value="<?= $year ?>">
      <input type="hidden" name="ho" value="<?= $month ?>">
      <div class="facets__filters">
        <details class="facet" data-facet>
          <summary class="facet__toggle">
            <span>Borvidék</span><?php if ($regionFilters): ?> <span class="facet__count"><?= count($regionFilters) ?></span><?php endif; ?>
            <svg class="facet__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <div class="facet__panel">
            <?php if (!$regionOptions): ?><p class="facet__empty">Nincs borvidék ebben a hónapban.</p><?php endif; ?>
            <?php foreach ($regionOptions as $slug => $name): ?>
              <label class="facet__opt">
                <input type="checkbox" name="borvidek[]" value="<?= h($slug) ?>"<?= in_array($slug, $regionFilters, true) ? ' checked' : '' ?>>
                <span><?= h($name) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>

        <details class="facet" data-facet>
          <summary class="facet__toggle">
            <span>Kategória</span><?php if ($catFilters): ?> <span class="facet__count"><?= count($catFilters) ?></span><?php endif; ?>
            <svg class="facet__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <div class="facet__panel">
            <?php if (!$catOptions): ?><p class="facet__empty">Nincs kategória ebben a hónapban.</p><?php endif; ?>
            <?php foreach ($catOptions as $slug => $name): ?>
              <label class="facet__opt">
                <input type="checkbox" name="kategoria[]" value="<?= h($slug) ?>"<?= in_array($slug, $catFilters, true) ? ' checked' : '' ?>>
                <span><?= h($name) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>

        <button type="submit" class="facets__btn">Szűrés</button>
        <?php if ($hasFacets): ?>
          <a class="facets__clear" href="naptar.php?ev=<?= $year ?>&amp;ho=<?= $month ?>">Szűrők törlése</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($catOptions): ?>
    <div class="cal-legend">
      <?php foreach ($catOptions as $slug => $name): [$bg, ] = categoryColorBySlug($slug); ?>
        <span class="cal-legend__item"><span class="cal-legend__dot" style="background: <?= h($bg) ?>"></span><?= h($name) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="cal">
      <div class="cal-toolbar">
        <span class="cal-toolbar__title">
          <span class="cal-toolbar__month"><?= h($monthTitle) ?></span>
          <span class="cal-toolbar__count"><?= $eventCount ?> esemény</span>
        </span>
        <div class="cal-nav">
          <a class="cal-nav__btn" href="<?= h($prevUrl) ?>" aria-label="Előző hónap">‹</a>
          <a class="cal-nav__btn" href="<?= h($nextUrl) ?>" aria-label="Következő hónap">›</a>
          <a class="cal-nav__today" href="<?= h($todayUrl) ?>">Ma</a>
        </div>
      </div>
      <div class="cal__dow"><span>Hét</span><span>Kedd</span><span>Sze</span><span>Csüt</span><span>Pén</span><span>Szo</span><span>Vas</span></div>
      <div class="cal__grid">
        <?php
        $leading = (int) $first->format('N') - 1;
        for ($i = 0; $i < $leading; $i++) {
            echo '<div class="cal__cell cal__cell--blank"></div>';
        }
        for ($d = 1; $d <= $daysInMonth; $d++):
            $isToday = ($year === (int) $now->format('Y') && $month === (int) $now->format('n') && $d === (int) $now->format('j'));
            $col = ($leading + ($d - 1)) % 7;
            $isWeekend = ($col >= 5);
            $dayEvents = $daysEvents[$d];
        ?>
          <div class="cal__cell<?= $isWeekend ? ' cal__cell--weekend' : '' ?><?= $isToday ? ' cal__cell--today' : '' ?>">
            <span class="cal__day"><?= $d ?></span>
            <?php if ($dayEvents): ?>
            <div class="cal__events">
              <?php foreach ($dayEvents as $e): [$bg, $fg] = categoryColor($e); ?>
                <a class="cal__event" style="background: <?= h($bg) ?>; color: <?= h($fg) ?>"
                   href="<?= h(eventUrl($e)) ?>" title="<?= h($e['title']) ?>"><?= h($e['title']) ?></a>
              <?php endforeach; ?>
              <?php if (count($dayEvents) > 3): ?>
                <span class="cal__more">+<?= count($dayEvents) - 3 ?> további</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
        <?php
        $trailing = (7 - (($leading + $daysInMonth) % 7)) % 7;
        for ($i = 0; $i < $trailing; $i++) {
            echo '<div class="cal__cell cal__cell--blank"></div>';
        }
        ?>
      </div>
    </div>

    <?php if (!$shown): ?>
      <p class="section-intro"><?= $monthEvents ? 'Nincs a szűrőnek megfelelő esemény ebben a hónapban.' : 'Ebben a hónapban nincs rögzített esemény.' ?>
        <a href="esemenyek.php">Nézd meg az összeset →</a></p>
    <?php endif; ?>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
