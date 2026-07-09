<?php
declare(strict_types=1);

// holborozzak.hu — Borvidékek áttekintő: mind a 22 magyar borvidék, közelgő esemény-számmal.
// Szép URL: /borvidekek. SEO: egyedi meta + ItemList/BreadcrumbList JSON-LD, belső linkek.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

logAiReferral();
logSearchReferral();

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$regions = [];
$counts = [];
try {
    $regions = db()->query('SELECT id, name, slug, image_url FROM wine_regions ORDER BY name')->fetchAll();
    foreach (fetchUpcomingEvents(db()) as $e) {
        $rs = (string) ($e['region_slug'] ?? '');
        if ($rs !== '') {
            $counts[$rs] = ($counts[$rs] ?? 0) + 1;
        }
    }
} catch (Throwable $e) {
    error_log('borvidekek.php DB hiba: ' . $e->getMessage());
}

$info = require __DIR__ . '/lib/regions_info.php';

// Magazin-mozaik: a legtöbb közelgő eseményt kínáló (max 2) borvidék nagy, 2×2-es csempét kap
$featured = [];
if ($counts) {
    $live = array_filter($counts);
    arsort($live);
    $featured = array_slice(array_keys($live), 0, 2);
}

$pageTitle = 'Magyar borvidékek — események borvidékenként | holborozzak.hu';
$pageDescription = 'Magyarország 22 borvidéke egy helyen: válaszd ki a borvidéket, és nézd meg '
    . 'a közelgő borrendezvényeit — Tokaji, Egri, Villányi, Badacsonyi és a többi.';
$activeNav = 'borvidekek';
$canonicalUrl = $base . $dir . '/borvidekek';

// Strukturált adat: a borvidék-oldalak ItemList-je + morzsa
$items = [];
$pos = 1;
foreach ($regions as $r) {
    $items[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'name'     => $r['name'] . ' borvidék',
        'url'      => $base . $dir . '/borvidek/' . rawurlencode($r['slug']),
    ];
}
$jsonLd = [
    [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Magyar borvidékek',
        'itemListElement' => $items,
    ],
    breadcrumbJsonLd([
        'Főoldal'    => $base . $dir . '/',
        'Borvidékek' => $canonicalUrl,
    ]),
];

$totalUpcoming = array_sum($counts);

require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <section class="events-section">
      <div class="page-head">
        <h1>Magyar borvidékek</h1>
        <p class="section-intro">Válaszd ki a borvidéket, és nézd meg a közelgő borrendezvényeit.
          Jelenleg <strong><?= (int) $totalUpcoming ?></strong> közelgő esemény <strong><?= count($regions) ?></strong> borvidéken.</p>
      </div>

      <?php if (!$regions): ?>
        <div class="rv-empty"><p>A borvidékek jelenleg nem érhetők el. Próbáld később.</p></div>
      <?php else: ?>
        <div class="region-grid">
          <?php foreach ($regions as $r):
              $c = (int) ($counts[$r['slug']] ?? 0);
              $g = $info[$r['slug']]['grapes'] ?? '';
              $img = regionImage($r['slug'], $r['image_url'] ?? null);
              $himg = $img['src'] !== '';
              $isFeat = in_array($r['slug'], $featured, true); ?>
            <a class="region-tile<?= $isFeat ? ' region-tile--feat' : '' ?><?= $himg ? '' : ' region-tile--noimg' ?>" href="borvidek/<?= h($r['slug']) ?>">
              <?php if ($himg): ?><img class="region-tile__bg" src="<?= h($img['src'] . $img['ver']) ?>" alt="" loading="lazy"><?php endif; ?>
              <?php if ($c > 0): ?><span class="region-tile__count"><?= $c ?> esemény</span><?php endif; ?>
              <span class="region-tile__body">
                <span class="region-tile__rule"></span>
                <span class="region-tile__name"><?= h($r['name']) ?></span>
                <?php if ($isFeat && $g !== ''): ?><span class="region-tile__grapes"><?= h($g) ?></span><?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
