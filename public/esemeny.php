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
if (!empty($event['image_url'])) {
    $ogImage = $base . $dir . '/' . ltrim($event['image_url'], '/');
}
$activeNav = 'esemenyek';

$ev = eventJsonLd($event, $base, $dir, $canonicalUrl);
$ev['@context'] = 'https://schema.org';
$jsonLd = [$ev];

$st = eventStatus($event['start_datetime'], $event['end_datetime']);

require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="event-detail">
      <a class="event-detail__back" href="./">← Vissza az eseményekhez</a>

      <?php if (!empty($event['image_url'])): ?>
        <img class="event-detail__img" src="<?= h($event['image_url']) ?>"
             alt="<?= h($event['image_alt'] ?: $event['title']) ?>">
      <?php endif; ?>

      <h1 class="event-detail__title"><?= h($event['title']) ?></h1>

      <p class="event-detail__meta">
        <time datetime="<?= h(isoDate($event['start_datetime'])) ?>"><?= h(formatDateRange($event['start_datetime'], $event['end_datetime'])) ?></time>
        <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
      </p>

      <p class="event-detail__loc">📍 <?= h(trim(($event['venue_name'] ? $event['venue_name'] . ', ' : '') . ($event['city'] ?? ''))) ?><?= $event['region_name'] ? ' · ' . h($event['region_name']) . ' borvidék' : '' ?></p>

      <div class="event-detail__tags">
        <?php foreach ($event['categories'] as $cat): ?><span class="tag"><?= h($cat['name']) ?></span><?php endforeach; ?>
        <?php if ((int) $event['is_free'] === 1): ?>
          <span class="tag tag--free">Ingyenes</span>
        <?php elseif (!empty($event['price_info'])): ?>
          <span class="tag"><?= h($event['price_info']) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($event['short_description'])): ?>
        <p class="event-detail__lead"><?= h($event['short_description']) ?></p>
      <?php endif; ?>

      <?php if (!empty($event['description'])): ?>
        <div class="event-detail__desc"><?= nl2br(h($event['description'])) ?></div>
      <?php endif; ?>

      <?php if (!empty($event['ticket_url']) || !empty($event['website_url'])): ?>
        <div class="event-detail__actions">
          <?php if (!empty($event['ticket_url'])): ?>
            <a class="btn btn--primary" href="<?= h(goUrl($event, 'ticket')) ?>" target="_blank" rel="noopener nofollow">Jegyek →</a>
          <?php endif; ?>
          <?php if (!empty($event['website_url'])): ?>
            <a class="btn btn--ghost" href="<?= h(goUrl($event, 'website')) ?>" target="_blank" rel="noopener nofollow">Hivatalos oldal →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
