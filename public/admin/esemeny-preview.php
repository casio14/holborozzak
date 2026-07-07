<?php
declare(strict_types=1);

// Esemény-előnézet (admin): bármely státuszú eseményt megmutat a publikus
// részletoldal kinézetével. Draftnál is működik (a publikus oldal csak published-öt mutat).

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$event = null;
try {
    $pdo = db();
    $sql = "SELECT e.*, r.name AS region_name, r.image_url AS region_image_url,
                   GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR '||') AS cat_names
            FROM events e
            LEFT JOIN wine_regions r ON r.id = e.region_id
            LEFT JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN categories c ON c.id = ec.category_id
            WHERE e.id = :id
            GROUP BY e.id
            LIMIT 1";
    $stm = $pdo->prepare($sql);
    $stm->execute([':id' => $id]);
    $event = $stm->fetch() ?: null;
} catch (Throwable $e) {
    error_log('esemeny-preview hiba: ' . $e->getMessage());
}

if (!$event) {
    http_response_code(404);
    echo 'Az esemény nem található. <a href="index.php">Vissza</a>';
    exit;
}

$cats = !empty($event['cat_names']) ? explode('||', (string) $event['cat_names']) : [];
$locText = trim((string) (($event['venue_name'] ? $event['venue_name'] . ', ' : '') . ($event['city'] ?? '')));
$priceText = (int) $event['is_free'] === 1 ? 'Ingyenes' : (!empty($event['price_info']) ? $event['price_info'] : '');
$st = eventStatus($event['start_datetime'], $event['end_datetime']);
$hasGeo = !empty($event['latitude']) && !empty($event['longitude']);
$mapsLink = $hasGeo
    ? ('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($event['latitude'] . ',' . $event['longitude']))
    : '';

$STATUS_LABEL = ['draft' => 'Beérkezett (draft)', 'published' => 'Közzétett', 'cancelled' => 'Lemondott'];
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();

/** Admin (/admin/) alól a relatív kép-utakat egy szinttel feljebbre igazítjuk. */
function pimg(string $src): string
{
    return preg_match('#^https?://#i', $src) ? $src : '../' . ltrim($src, '/');
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Előnézet: <?= h($event['title']) ?> — admin</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
<?php if ($hasGeo): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<?php endif; ?>
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">Esemény-előnézet — <?= h($STATUS_LABEL[$event['status']] ?? $event['status']) ?></span>
    <span class="admin-bar__links"><a href="edit.php?id=<?= $id ?>">Szerkesztés</a> <a href="index.php">← Vissza</a></span>
  </div>

  <article class="edh-detail">
    <div class="edh">
      <div class="edh__img">
        <img src="<?= h(pimg(eventImage($event))) ?>" alt="<?= h($event['image_alt'] ?: $event['title']) ?>">
      </div>
      <div class="edh__inner">
        <a class="edh__back" href="index.php">← Vissza a listához</a>
        <div class="edh__tags">
          <?php if ((int) $event['is_featured'] === 1): ?><span class="tag tag--featured">★ Kiemelt</span><?php endif; ?>
          <?php foreach ($cats as $cn): ?><span class="tag"><?= h($cn) ?></span><?php endforeach; ?>
        </div>
        <h1><?= h($event['title']) ?></h1>
        <div class="edh__when">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>
          <time datetime="<?= h(isoDate($event['start_datetime'])) ?>"><?= h(formatDateRange($event['start_datetime'], $event['end_datetime'])) ?></time>
          <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="edh-layout">
      <div class="edh-main">
        <?php if (!empty($event['short_description'])): ?>
          <p class="edh-lead"><?= h($event['short_description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($event['description'])): ?>
          <div class="event-detail__desc"><?= renderDescription($event['description']) ?></div>
        <?php endif; ?>
      </div>

      <aside class="edh-aside">
        <div class="edh-card">
          <div class="ed-info ed-info--flat">
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
              <a class="btn btn--primary" href="<?= h($event['ticket_url']) ?>" target="_blank" rel="noopener nofollow">Jegyek →</a>
            <?php endif; ?>
            <?php if (!empty($event['website_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($event['website_url']) ?>" target="_blank" rel="noopener nofollow">Hivatalos oldal →</a>
            <?php endif; ?>
            <?php if (!empty($event['facebook_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($event['facebook_url']) ?>" target="_blank" rel="noopener nofollow">Facebook-esemény →</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="../ics.php?e=<?= (int) $event['id'] ?>">＋ Naptárhoz adom</a>
          </div>

          <?php if ($hasGeo): ?>
            <div id="event-map" class="edh-map" role="region" aria-label="Az esemény helyszíne térképen"></div>
            <p class="ed-map__link"><a href="<?= h($mapsLink) ?>" target="_blank" rel="noopener nofollow">Megnyitás Google Mapsben →</a></p>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </article>
<?php if ($hasGeo): ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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
</body>
</html>
