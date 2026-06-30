<?php
declare(strict_types=1);

// Jelölt-előnézet: hogyan nézne ki az esemény a publikus részletoldalon.
// A valódi részletoldal CSS-osztályait használja (event-detail--hero, ed-*).

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require __DIR__ . '/../lib/candidates.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$cand = null;
$regionImage = null;
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM event_candidates WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $cand = $st->fetch() ?: null;
    if ($cand && !empty($cand['region_name'])) {
        $needle = foldText((string) $cand['region_name']);
        foreach ($pdo->query('SELECT name, image_url FROM wine_regions') as $r) {
            $rn = foldText((string) $r['name']);
            if ($rn === $needle || strpos($rn, $needle) !== false || strpos($needle, $rn) !== false) {
                $regionImage = $r['image_url'] ?: null;
                break;
            }
        }
    }
} catch (Throwable $e) {
    error_log('jelolt-preview hiba: ' . $e->getMessage());
}

if (!$cand) {
    http_response_code(404);
    echo 'A jelölt nem található. <a href="jeloltek.php">Vissza</a>';
    exit;
}

// Esemény-szerű tömb a megjelenítő segédfüggvényekhez
$ev = [
    'image_url'        => $cand['image_url'],
    'region_image_url' => $regionImage,
];
$locText = trim((string) (($cand['venue_name'] ? $cand['venue_name'] . ', ' : '') . ($cand['city'] ?? '')));
$priceText = (int) $cand['is_free'] === 1 ? 'Ingyenes' : (!empty($cand['price_info']) ? $cand['price_info'] : '');
$st = $cand['start_datetime'] ? eventStatus($cand['start_datetime'], $cand['end_datetime']) : null;
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Előnézet: <?= h($cand['title']) ?> — admin</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">Jelölt-előnézet (nem publikus)</span>
    <span><a href="jeloltek.php">← Vissza a jelöltekhez</a></span>
  </div>

  <div class="container">
    <article class="event-detail event-detail--hero">
      <div class="ed-hero">
        <div class="ed-hero__img">
          <img src="<?= h(eventImage($ev)) ?>" alt="<?= h($cand['title']) ?>">
        </div>
        <div class="ed-hero__band">
          <h1><?= h($cand['title']) ?></h1>
          <?php if ($cand['start_datetime']): ?>
          <div class="ed-hero__when">
            <time datetime="<?= h(isoDate($cand['start_datetime'])) ?>"><?= h(formatDateRange($cand['start_datetime'], $cand['end_datetime'])) ?></time>
            <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="ed-grid">
        <div class="ed-main">
          <?php if (!empty($cand['short_description'])): ?>
            <p class="event-detail__lead"><?= h($cand['short_description']) ?></p>
          <?php endif; ?>
          <?php if (!empty($cand['description'])): ?>
            <div class="event-detail__desc"><?= nl2br(h($cand['description'])) ?></div>
          <?php else: ?>
            <p class="event-detail__desc admin-sub">(Nincs részletes leírás — jóváhagyás után a szerkesztőben pótolható.)</p>
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
            <?php if (!empty($cand['region_name'])): ?>
            <div class="ed-info__row">
              <span class="ed-info__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6.5 9 3.5 15 6.5 21 3.5 21 17.5 15 20.5 9 17.5 3 20.5"/><line x1="9" y1="3.5" x2="9" y2="17.5"/><line x1="15" y1="6.5" x2="15" y2="20.5"/></svg></span>
              <span><span class="ed-info__k">Borvidék</span><span class="ed-info__v"><?= h($cand['region_name']) ?></span></span>
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
            <?php if (!empty($cand['ticket_url'])): ?>
              <a class="btn btn--primary" href="<?= h($cand['ticket_url']) ?>" target="_blank" rel="noopener nofollow">Jegyek →</a>
            <?php endif; ?>
            <?php if (!empty($cand['website_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($cand['website_url']) ?>" target="_blank" rel="noopener nofollow">Hivatalos oldal →</a>
            <?php endif; ?>
            <?php if (!empty($cand['facebook_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($cand['facebook_url']) ?>" target="_blank" rel="noopener nofollow">Facebook-esemény →</a>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </article>
  </div>
</body>
</html>
