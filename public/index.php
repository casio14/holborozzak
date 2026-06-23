<?php
declare(strict_types=1);

// holborozzak.hu — kezdőoldal: kiemelt események + hónapokra bontott lista (DB-ből).

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

$pageTitle = 'holborozzak.hu — Magyarország borrendezvényei egy helyen';
$pageDescription = 'Fedezd fel Magyarország legjobb bor-eseményeit: fesztiválok, kóstolók '
    . 'és pincelátogatások Tokajtól Villányig — egy helyen, mindig naprakészen.';
$activeNav = 'esemenyek';

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

// Aktív nézet (tab) + fazetta-szűrők (borvidék, kategória)
$view = normalizeView($_GET['nezet'] ?? null);
$regionFilters = array_values(array_filter(array_map('strval', (array) ($_GET['borvidek'] ?? [])), 'strlen'));
$catFilters    = array_values(array_filter(array_map('strval', (array) ($_GET['kategoria'] ?? [])), 'strlen'));
$sort          = normalizeSort($_GET['rendezes'] ?? null);

// Szűrő legördülők opciói a közelgő eseményekből (csak releváns értékek)
$regionOptions = [];
$catOptions = [];
foreach ($events as $e) {
    if (!empty($e['region_slug'])) {
        $regionOptions[$e['region_slug']] = $e['region_name'];
    }
    foreach ($e['categories'] as $c) {
        $catOptions[$c['slug']] = $c['name'];
    }
}
asort($regionOptions);
asort($catOptions);

$displayEvents = applyFacets(filterEvents($events, $view), $regionFilters, $catFilters);
$hasFacets = (!empty($regionFilters) || !empty($catFilters));

// Kiemelt blokk csak a tiszta alap nézeten (nincs aktív szűrő)
$showFeatured = ($view === 'kozelgo' && !$hasFacets);
$featured = $showFeatured
    ? array_values(array_filter($events, static fn($e) => (int) $e['is_featured'] === 1))
    : [];

// A megjelenített események csoportjai (rendezés szerint)
$groups = groupEventsForList($displayEvents, $sort);

$listHeading = ($view === 'kozelgo') ? 'Közelgő események' : EVENT_VIEWS[$view];

// --- Strukturált adat: ItemList az eseményekről (SEO / AI-kereső) ---
$ld = eventsItemListJsonLd($events, $base, $dir);
if ($ld) {
    $jsonLd = $ld;
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

    <div id="esemenyek-region">
    <nav class="tabs" aria-label="Esemény nézetek">
      <?php foreach (EVENT_VIEWS as $key => $label): ?>
        <a href="<?= h(listUrl($key, $regionFilters, $catFilters, $sort)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <form class="facets" method="get" action="" aria-label="Szűrők és rendezés">
      <?php if ($view !== 'kozelgo'): ?><input type="hidden" name="nezet" value="<?= h($view) ?>"><?php endif; ?>

      <div class="facets__filters">
        <details class="facet" data-facet>
          <summary class="facet__toggle">
            <span>Borvidék</span><?php if ($regionFilters): ?> <span class="facet__count"><?= count($regionFilters) ?></span><?php endif; ?>
            <svg class="facet__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <div class="facet__panel">
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
          <a class="facets__clear" href="<?= h(listUrl($view, [], [], $sort)) ?>">Szűrők törlése</a>
        <?php endif; ?>
      </div>

      <div class="facets__sort">
        <label class="facets__sort-label" for="rendezes-select">Rendezés:</label>
        <select class="facet-select facet-select--sort" id="rendezes-select" name="rendezes" aria-label="Rendezés">
          <?php foreach (EVENT_SORTS as $sortKey => $sortLabel): ?>
            <option value="<?= h($sortKey) ?>"<?= $sort === $sortKey ? ' selected' : '' ?>><?= h($sortLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

  <?php if ($featured): ?>
    <section class="events-section">
      <div class="events-section__head"><h2>Kiemelt események</h2></div>
      <div class="events-grid">
        <?php foreach ($featured as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
          <article class="event-card">
            <a class="event-card__media" href="<?= h(eventUrl($e)) ?>">
              <img src="<?= h($e['image_url'] ?: 'assets/hero.jpg') ?>" alt="<?= h($e['image_alt'] ?: $e['title']) ?>" loading="lazy">
              <span class="event-card__badge">Kiemelt</span>
            </a>
            <div class="event-card__body">
              <p class="event-card__date">
                <time datetime="<?= h(isoDate($e['start_datetime'])) ?>"><?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?></time>
                <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
              </p>
              <h3 class="event-card__title"><a href="<?= h(eventUrl($e)) ?>"><?= h($e['title']) ?></a></h3>
              <p class="event-card__meta">📍 <?= h(trim(($e['venue_name'] ? $e['venue_name'] . ', ' : '') . $e['city'])) ?></p>
              <div class="event-card__tags">
                <?php foreach ($e['categories'] as $cat): ?><span class="tag"><?= h($cat['name']) ?></span><?php endforeach; ?>
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
      <?php if (!$groups): ?>
        <p class="section-intro">Nincs a szűrőnek megfelelő esemény. <a href="<?= h(listUrl('kozelgo', [], [])) ?>">Összes közelgő →</a></p>
      <?php else: ?>
      <div class="events-list">
        <?php foreach ($groups as $group): ?>
          <?php if ($group['label'] !== null): ?>
          <div class="events-list__month">
            <span class="events-list__dot" style="background: <?= h($group['dot']) ?>"></span>
            <?= h($group['label']) ?>
          </div>
          <?php endif; ?>
          <?php foreach ($group['events'] as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
            <a class="event-row" href="<?= h(eventUrl($e)) ?>">
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
                  <?php if (!empty($e['categories'])): ?> · <?= h(implode(', ', categoryNames($e))) ?><?php endif; ?>
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
    </div><!-- #esemenyek-region -->

<?php endif; ?>

  </div>
<?php
require __DIR__ . '/partials/footer.php';
