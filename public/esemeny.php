<?php
declare(strict_types=1);

// holborozzak.hu — esemény részletoldal (slug alapján).

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$slug = trim((string) ($_GET['slug'] ?? ''));
$event = null;
if ($slug !== '') {
    try {
        $event = fetchEventBySlug(db(), $slug);
    } catch (Throwable $e) {
        error_log('esemeny.php DB hiba: ' . $e->getMessage());
    }
}

// --- Nem található ---
if (!$event) {
    http_response_code(404);
    $pageTitle = 'Esemény nem található — holborozzak.hu';
    $robots = 'noindex,follow';
    $activeNav = 'esemenyek';
    require __DIR__ . '/partials/header.php';
    ?>
      <div class="container">
        <section class="events-section">
          <h1>Az esemény nem található</h1>
          <p class="section-intro">Lehet, hogy lejárt vagy megszűnt.
            <a href="./">Vissza az eseményekhez →</a></p>
        </section>
      </div>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

// --- Megtalált esemény ---
$canonicalUrl = eventUrl($event, $base, $dir);
$pageTitle = $event['title'] . ' — holborozzak.hu';
$pageDescription = $event['short_description']
    ?: ('Borrendezvény: ' . $event['title'] . (!empty($event['city']) ? ' — ' . $event['city'] : ''));
$ogType = 'article';
if (!empty($event['image_url']) || !empty($event['region_image_url'])) {
    $ogImage = $base . $dir . '/' . ltrim(eventImage($event), '/');
}
$activeNav = 'esemenyek';

$ev = eventJsonLd($event, $base, $dir, $canonicalUrl);
$ev['@context'] = 'https://schema.org';
$jsonLd = [$ev, breadcrumbJsonLd([
    'Főoldal'   => $base . $dir . '/',
    'Események' => $base . $dir . '/esemenyek.php',
    $event['title'] => $canonicalUrl,
])];

$st = eventStatus($event['start_datetime'], $event['end_datetime']);

// Térkép csak akkor, ha van koordináta
$hasGeo = !empty($event['latitude']) && !empty($event['longitude']);
$mapsLink = $hasGeo
    ? ('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($event['latitude'] . ',' . $event['longitude']))
    : '';
if ($hasGeo) {
    $headExtra = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"'
        . ' integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';
}

$locText = trim(($event['venue_name'] ? $event['venue_name'] . ', ' : '') . ($event['city'] ?? ''));
$priceText = (int) $event['is_free'] === 1 ? 'Ingyenes' : (!empty($event['price_info']) ? $event['price_info'] : '');

require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="event-detail event-detail--hero">
      <a class="event-detail__back" href="./">← Vissza az eseményekhez</a>

      <div class="ed-hero">
        <div class="ed-hero__img">
          <img src="<?= h(eventImage($event)) ?>" alt="<?= h($event['image_alt'] ?: $event['title']) ?>">
          <?php if ((int) $event['is_featured'] === 1): ?><span class="ed-hero__badge">Kiemelt</span><?php endif; ?>
        </div>
        <div class="ed-hero__band">
          <h1><?= h($event['title']) ?></h1>
          <div class="ed-hero__when">
            <time datetime="<?= h(isoDate($event['start_datetime'])) ?>"><?= h(formatDateRange($event['start_datetime'], $event['end_datetime'])) ?></time>
            <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="ed-grid">
        <div class="ed-main">
          <?php if (!empty($event['categories'])): ?>
          <div class="event-detail__tags">
            <?php foreach ($event['categories'] as $cat): ?><span class="tag"><?= h($cat['name']) ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($event['short_description'])): ?>
            <p class="event-detail__lead"><?= h($event['short_description']) ?></p>
          <?php endif; ?>

          <?php if (!empty($event['description'])): ?>
            <div class="event-detail__desc"><?= nl2br(h($event['description'])) ?></div>
          <?php endif; ?>
        </div>

        <aside class="ed-aside">
          <div class="ed-info">
            <?php if ($locText !== ''): ?>
            <div class="ed-info__row">
              <span class="ed-info__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.3 7-12a7 7 0 1 0-14 0c0 5.7 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
              <span><span class="ed-info__k">Hol</span><span class="ed-info__v"><?= h($locText) ?></span></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($event['region_name'])): ?>
            <div class="ed-info__row">
              <span class="ed-info__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6.5 9 3.5 15 6.5 21 3.5 21 17.5 15 20.5 9 17.5 3 20.5"/><line x1="9" y1="3.5" x2="9" y2="17.5"/><line x1="15" y1="6.5" x2="15" y2="20.5"/></svg></span>
              <span><span class="ed-info__k">Borvidék</span><span class="ed-info__v"><?= h($event['region_name']) ?></span></span>
            </div>
            <?php endif; ?>
            <?php if ($priceText !== ''): ?>
            <div class="ed-info__row">
              <span class="ed-info__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13 21l-9-9V4h8z"/><circle cx="7.5" cy="7.5" r="1.3" fill="currentColor" stroke="none"/></svg></span>
              <span><span class="ed-info__k">Ár</span><span class="ed-info__v"><?= h($priceText) ?></span></span>
            </div>
            <?php endif; ?>
          </div>

          <div class="ed-actions">
            <?php if (!empty($event['ticket_url'])): ?>
              <a class="btn btn--primary" href="<?= h(goUrl($event, 'ticket')) ?>" target="_blank" rel="noopener nofollow">Jegyek →</a>
            <?php endif; ?>
            <?php if (!empty($event['website_url'])): ?>
              <a class="btn btn--ghost" href="<?= h(goUrl($event, 'website')) ?>" target="_blank" rel="noopener nofollow">Hivatalos oldal →</a>
            <?php endif; ?>
            <?php if (!empty($event['facebook_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($event['facebook_url']) ?>" target="_blank" rel="noopener nofollow">Facebook-esemény →</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="ics.php?e=<?= (int) $event['id'] ?>">＋ Naptárhoz adom</a>
          </div>
        </aside>

        <?php if ($hasGeo): ?>
        <div class="ed-map">
          <div id="event-map" class="ed-map__canvas" role="region" aria-label="Az esemény helyszíne térképen"></div>
          <p class="ed-map__link"><a href="<?= h($mapsLink) ?>" target="_blank" rel="noopener nofollow">Megnyitás Google Mapsben / útvonalterv →</a></p>
        </div>
        <?php endif; ?>
      </div>
    </article>
  </div>
<?php if ($hasGeo): ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
  (function () {
    if (typeof L === 'undefined') { return; }
    var lat = <?= json_encode((float) $event['latitude']) ?>, lng = <?= json_encode((float) $event['longitude']) ?>;
    var map = L.map('event-map', { scrollWheelZoom: false }).setView([lat, lng], 13);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      maxZoom: 19, subdomains: 'abcd', attribution: '&copy; OpenStreetMap, &copy; CARTO'
    }).addTo(map);
    var dot = L.divIcon({ className: 'grape-dot', html: '', iconSize: [18, 18], iconAnchor: [9, 9] });
    L.marker([lat, lng], { icon: dot }).addTo(map);
  })();
  </script>
<?php endif; ?>
<?php
require __DIR__ . '/partials/footer.php';
