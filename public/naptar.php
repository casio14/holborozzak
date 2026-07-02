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

// Hét-sorok (0 = hónapon kívüli üres cella) az átívelő sávos rácshoz
$leading = (int) $first->format('N') - 1;
$cells = array_fill(0, $leading, 0);
for ($d = 1; $d <= $daysInMonth; $d++) { $cells[] = $d; }
while (count($cells) % 7 !== 0) { $cells[] = 0; }
$weeks = array_chunk($cells, 7);

// Sáv-adatok: a hónapra vágott kezdő/zárónap + folytatás-jelzők (előző/következő hónapba lóg)
$bars = [];
foreach ($shown as $e) {
    $s  = new DateTimeImmutable($e['start_datetime']);
    $en = !empty($e['end_datetime']) ? new DateTimeImmutable($e['end_datetime']) : $s;
    if ($en < $s) { $en = $s; }
    $bars[] = [
        'e'    => $e,
        'sd'   => ($s < $monthStart) ? 1 : (int) $s->format('j'),
        'ed'   => ($en > $monthEnd) ? $daysInMonth : (int) $en->format('j'),
        'pre'  => $s < $monthStart,
        'post' => $en > $monthEnd,
    ];
}
// Kezdőnap szerint növekvő, azonos kezdésnél a hosszabb esemény előre (szebb rétegződés)
usort($bars, static fn(array $a, array $b) => [$a['sd'], $b['ed']] <=> [$b['sd'], $a['ed']]);

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

      <?php if ($catOptions): ?>
      <div class="cal-legend">
        <?php foreach ($catOptions as $slug => $name): [$bg, ] = categoryColorBySlug($slug); ?>
          <span class="cal-legend__item"><span class="cal-legend__dot" style="background: <?= h($bg) ?>"></span><?= h($name) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="cal__dow"><span>Hétfő</span><span>Kedd</span><span>Szerda</span><span>Csüt</span><span>Péntek</span><span>Szombat</span><span>Vasárnap</span></div>

      <?php foreach ($weeks as $week): ?>
        <?php
        $weekDays  = array_values(array_filter($week));
        $weekFirst = $weekDays ? min($weekDays) : 0;
        $weekLast  = $weekDays ? max($weekDays) : 0;
        ?>
        <div class="cal__week">
          <?php foreach ($week as $i => $d):
              $isToday = $d && $year === (int) $now->format('Y') && $month === (int) $now->format('n') && $d === (int) $now->format('j'); ?>
            <span class="cal__d<?= $i >= 5 ? ' cal__d--we' : '' ?><?= $isToday ? ' cal__d--today' : '' ?>"><?= $d ? ($isToday ? '<b>' . $d . '</b>' : $d) : '' ?></span>
          <?php endforeach; ?>

          <?php foreach ($bars as $bar):
              if (!$weekFirst) { continue; }
              $segS = max($bar['sd'], $weekFirst);
              $segE = min($bar['ed'], $weekLast);
              if ($segS > $segE) { continue; }
              $e     = $bar['e'];
              $col   = (($leading + $segS - 1) % 7) + 1;
              $span  = $segE - $segS + 1;
              $contL = $bar['pre'] || $segS > $bar['sd'];
              $contR = $bar['post'] || $segE < $bar['ed'];
              [$bg, $fg] = categoryColor($e);
              $tipRight  = ($col + $span - 1) >= 5;
              $loc       = trim(($e['venue_name'] ? $e['venue_name'] . ', ' : '') . ($e['city'] ?? ''));
              $price     = (int) $e['is_free'] === 1 ? 'Ingyenes' : (string) ($e['price_info'] ?? '');
          ?>
            <a class="cal__bar<?= $contL ? ' cal__bar--cont-l' : '' ?><?= $contR ? ' cal__bar--cont-r' : '' ?>"
               style="grid-column: <?= $col ?> / span <?= $span ?>; background: <?= h($bg) ?>; color: <?= h($fg) ?>"
               href="<?= h(eventUrl($e)) ?>">
              <span class="cal__bar-t"><?= h($e['title']) ?></span>
              <span class="cal-tip<?= $tipRight ? ' cal-tip--r' : '' ?>">
                <img class="cal-tip__img" src="<?= h(eventImage($e)) ?>" alt="" loading="lazy">
                <span class="cal-tip__body">
                  <b><?= h($e['title']) ?></b>
                  <span class="cal-tip__date"><?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?></span>
                  <?php if ($loc !== ''): ?><span class="cal-tip__loc">📍 <?= h($loc) ?></span><?php endif; ?>
                  <?php if ($price !== '' && (int) $e['is_free'] !== 1): ?><span class="cal-tip__price"><?= h($price) ?></span><?php endif; ?>
                  <?php if ($e['categories'] || (int) $e['is_free'] === 1): ?>
                  <span class="cal-tip__tags">
                    <?php foreach (array_slice($e['categories'], 0, 2) as $c): ?><span class="tag"><?= h($c['name']) ?></span><?php endforeach; ?>
                    <?php if ((int) $e['is_free'] === 1): ?><span class="tag tag--free">Ingyenes</span><?php endif; ?>
                  </span>
                  <?php endif; ?>
                  <span class="cal-tip__btn">Részletek →</span>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Mobil: agenda-lista a rács helyett (kis kijelzőn a 7 oszlop olvashatatlan) -->
    <?php if ($shown): ?>
    <div class="cal-agenda">
      <?php foreach ($bars as $bar): $e = $bar['e']; ?>
        <a class="cal-agenda__row" href="<?= h(eventUrl($e)) ?>">
          <span class="cal-agenda__date">
            <b><?= h(dayNumber($e['start_datetime'])) ?></b>
            <i><?= h(shortMonthUpper($e['start_datetime'])) ?></i>
          </span>
          <span class="cal-agenda__main">
            <span class="cal-agenda__title"><?= h($e['title']) ?></span>
            <span class="cal-agenda__sub">
              <?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?><?php if (!empty($e['city'])): ?> · <?= h($e['city']) ?><?php endif; ?><?php if ((int) $e['is_free'] === 1): ?> · Ingyenes<?php endif; ?>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$shown): ?>
      <p class="section-intro"><?= $monthEvents ? 'Nincs a szűrőnek megfelelő esemény ebben a hónapban.' : 'Ebben a hónapban nincs rögzített esemény.' ?>
        <a href="esemenyek.php">Nézd meg az összeset →</a></p>
    <?php endif; ?>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
