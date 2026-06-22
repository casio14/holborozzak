<?php
declare(strict_types=1);

// holborozzak.hu — kezdőoldal: kiemelt események + hónapokra bontott lista (DB-ből).

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

$pageTitle = 'holborozzak.hu — Magyarország borrendezvényei egy helyen';
$pageDescription = 'Fedezd fel Magyarország legjobb bor-eseményeit: fesztiválok, kóstolók '
    . 'és pincelátogatások Tokajtól Villányig — egy helyen, mindig naprakészen.';

// Abszolút bázis URL a JSON-LD / képek számára
$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'); // pl. /borozzak

// Adatok betöltése (hiba esetén üres lista, log)
$events = [];
try {
    $events = fetchUpcomingEvents(db());
} catch (Throwable $e) {
    error_log('index.php DB hiba: ' . $e->getMessage());
}

// Aktív nézet (tab) és a szerinti szűrés
$view = normalizeView($_GET['nezet'] ?? null);
$displayEvents = filterEvents($events, $view);

// Kiemelt blokk csak az alap (Közelgő) nézeten jelenik meg
$showFeatured = ($view === 'kozelgo');
$featured = $showFeatured
    ? array_values(array_filter($events, static fn($e) => (int) $e['is_featured'] === 1))
    : [];

// A megjelenített (szűrt) események hónapokra bontva
$byMonth = [];
foreach ($displayEvents as $e) {
    $byMonth[monthKey($e['start_datetime'])][] = $e;
}
ksort($byMonth);

$listHeading = ($view === 'kozelgo') ? 'Közelgő események' : EVENT_VIEWS[$view];

// --- Strukturált adat: ItemList az eseményekről (SEO / AI-kereső) ---
$itemList = [];
$pos = 1;
foreach ($events as $e) {
    $img = $e['image_url'] ?? '';
    $imgAbs = $img ? ($base . $dir . '/' . ltrim($img, '/')) : null;
    $event = [
        '@type'     => 'Event',
        'name'      => $e['title'],
        'startDate' => isoDate($e['start_datetime']),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location'  => [
            '@type'   => 'Place',
            'name'    => $e['venue_name'] ?: ($e['city'] ?? ''),
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $e['address'] ?? '',
                'addressLocality' => $e['city'] ?? '',
                'addressCountry'  => 'HU',
            ],
        ],
    ];
    if (!empty($e['end_datetime']))  { $event['endDate'] = isoDate($e['end_datetime']); }
    if ($imgAbs)                     { $event['image'] = $imgAbs; }
    if (!empty($e['short_description'])) { $event['description'] = $e['short_description']; }
    if (!empty($e['latitude']) && !empty($e['longitude'])) {
        $event['location']['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $e['latitude'],
            'longitude' => (float) $e['longitude'],
        ];
    }
    if ((int) $e['is_free'] === 1) {
        $event['offers'] = ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'HUF',
                            'availability' => 'https://schema.org/InStock'];
    }
    $itemList[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => $event];
}
if ($itemList) {
    $jsonLd = [[
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Közelgő borrendezvények Magyarországon',
        'itemListElement' => $itemList,
    ]];
}

require __DIR__ . '/partials/header.php';
?>
  <section class="hero">
    <div class="hero__inner">
      <p class="hero__eyebrow">Magyarország borrendezvényei</p>
      <h1>Fedezd fel az ország legjobb bor-eseményeit</h1>
      <p class="hero__lead">
        Fesztiválok, kóstolók és pincelátogatások Tokajtól Villányig
        — egy helyen, mindig naprakészen.
      </p>
      <form class="hero__search" role="search" method="get" action="">
        <input id="hero-kereso" type="search" name="q"
               placeholder="Keresés helyszín, borvidék vagy esemény szerint…"
               aria-label="Keresés helyszín, borvidék vagy esemény szerint">
        <button type="submit">Keresés</button>
      </form>
    </div>
  </section>

  <div class="container">

<?php if (!$events): ?>
    <p class="section-intro">Hamarosan kerülnek fel az események. 🍷</p>
<?php else: ?>

    <nav class="tabs" aria-label="Esemény szűrők">
      <?php foreach (EVENT_VIEWS as $key => $label): ?>
        <a href="?nezet=<?= h($key) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>

  <?php if ($featured): ?>
    <section class="events-section">
      <div class="events-section__head"><h2>Kiemelt események</h2></div>
      <div class="events-grid">
        <?php foreach ($featured as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
          <article class="event-card">
            <a class="event-card__media" href="#">
              <img src="<?= h($e['image_url'] ?: 'assets/hero.jpg') ?>" alt="<?= h($e['image_alt'] ?: $e['title']) ?>" loading="lazy">
              <span class="event-card__badge">Kiemelt</span>
            </a>
            <div class="event-card__body">
              <p class="event-card__date">
                <time datetime="<?= h(isoDate($e['start_datetime'])) ?>"><?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?></time>
                <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
              </p>
              <h3 class="event-card__title"><a href="#"><?= h($e['title']) ?></a></h3>
              <p class="event-card__meta">📍 <?= h(trim(($e['venue_name'] ? $e['venue_name'] . ', ' : '') . $e['city'])) ?></p>
              <div class="event-card__tags">
                <?php foreach ($e['categories'] as $cat): ?><span class="tag"><?= h($cat) ?></span><?php endforeach; ?>
                <?php if ((int) $e['is_free'] === 1): ?><span class="tag tag--free">Ingyenes</span><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

    <section class="events-section">
      <div class="events-section__head"><h2><?= h($listHeading) ?></h2></div>
      <?php if (!$byMonth): ?>
        <p class="section-intro">Nincs a szűrőnek megfelelő esemény. <a href="?nezet=kozelgo">Összes közelgő →</a></p>
      <?php else: ?>
      <div class="events-list">
        <?php foreach ($byMonth as $key => $monthEvents): ?>
          <div class="events-list__month">
            <span class="events-list__dot" style="background: <?= h(monthDotColor($monthEvents[0]['start_datetime'])) ?>"></span>
            <?= h(monthLabel($monthEvents[0]['start_datetime'])) ?>
          </div>
          <?php foreach ($monthEvents as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
            <a class="event-row" href="#">
              <span class="date-block">
                <span class="date-block__day"><?= h(dayNumber($e['start_datetime'])) ?></span>
                <span class="date-block__mon"><?= h(shortMonthUpper($e['start_datetime'])) ?></span>
              </span>
              <span class="event-row__main">
                <span class="event-row__title">
                  <?= h($e['title']) ?>
                  <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
                  <?php if ((int) $e['is_free'] === 1): ?><span class="status is-free">Ingyenes</span><?php endif; ?>
                </span>
                <span class="event-row__sub">
                  <?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?>
                  <?php if (!empty($e['categories'])): ?> · <?= h(implode(', ', $e['categories'])) ?><?php endif; ?>
                </span>
              </span>
              <span class="event-row__right">
                <span class="event-row__loc"><?= h($e['city']) ?><?= $e['region_name'] ? ' · ' . h($e['region_name']) : '' ?></span>
                <span class="event-row__chev">→</span>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

<?php endif; ?>

  </div>
<?php
require __DIR__ . '/partials/footer.php';
