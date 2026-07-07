<?php
declare(strict_types=1);

// holborozzak.hu — borvidék-oldal: egy borvidék bemutatása + közelgő eseményei.
// Szép URL: /borvidek/<slug> (.htaccess rewrite). SEO: egyedi meta + ItemList/BreadcrumbList JSON-LD.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

logAiReferral();
logSearchReferral();

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

// Slug a query-ből, tartalékként az útvonalból (/borvidek/<slug>)
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' && preg_match('#/borvidek/([^/?]+)#', (string) ($_SERVER['REQUEST_URI'] ?? ''), $m)) {
    $slug = trim(urldecode($m[1]));
}

$region = null;
if ($slug !== '') {
    try {
        $st = db()->prepare('SELECT id, name, slug, image_url, image_alt FROM wine_regions WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $region = $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('borvidek.php DB hiba: ' . $e->getMessage());
    }
}

// --- Nem található ---
if (!$region) {
    http_response_code(404);
    $pageTitle = 'Borvidék nem található — holborozzak.hu';
    $robots = 'noindex,follow';
    $activeNav = 'borvidekek';
    require __DIR__ . '/partials/header.php';
    ?>
      <div class="container">
        <section class="events-section">
          <h1>Ez a borvidék nem található</h1>
          <p class="section-intro">Nézd meg az összes borvidéket.
            <a href="borvidekek">Borvidékek →</a></p>
        </section>
      </div>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

// A régi borvidek.php?slug=… → 301 a szép URL-re (egyetlen kanonikus cím)
if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'borvidek.php') !== false) {
    header('Location: ' . $base . $dir . '/borvidek/' . rawurlencode($region['slug']), true, 301);
    exit;
}

// Borvidék közelgő eseményei (az összes közelgőből szűrve, hogy megmaradjon a kategória-adat)
$regionEvents = [];
try {
    foreach (fetchUpcomingEvents(db()) as $e) {
        if (($e['region_slug'] ?? '') === $region['slug']) {
            $regionEvents[] = $e;
        }
    }
} catch (Throwable $e) {
    error_log('borvidek.php esemény-lekérdezés hiba: ' . $e->getMessage());
}

$info = require __DIR__ . '/lib/regions_info.php';
$ri   = $info[$region['slug']] ?? [];
$intro = $ri['intro'] ?? ('A(z) ' . $region['name'] . ' borvidék közelgő borrendezvényei egy helyen — fesztiválok, bornapok, kóstolók, szüreti programok.');

// Hero-kép forrása, prioritás szerint:
//  1) a DB wine_regions.image_url (ha be van állítva),
//  2) konvenció: assets/borvidek/<slug>.(webp|jpg|jpeg|png) — elég ide feltölteni a képet,
//  3) különben a lenti dekoratív szőlőhegy-SVG.
$regImg = trim((string) ($region['image_url'] ?? ''));
if ($regImg === '') {
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $rel = 'assets/borvidek/' . $region['slug'] . '.' . $ext;
        if (is_file(__DIR__ . '/' . $rel)) { $regImg = $rel; break; }
    }
}
$hasImg = $regImg !== '';

// SEO
$canonicalUrl = $base . $dir . '/borvidek/' . rawurlencode($region['slug']);
$pageTitle = $region['name'] . ' borvidék — borrendezvények és programok | holborozzak.hu';
$pageDescription = mb_substr($intro, 0, 155);
$ogType = 'website';
if ($hasImg) {
    $ogImage = preg_match('#^https?://#i', $regImg) ? $regImg : ($base . $dir . '/' . ltrim($regImg, '/'));
}
$activeNav = 'borvidekek';

// Strukturált adat: a borvidék eseményeinek ItemList + morzsa
$jsonLd = array_merge(
    eventsItemListJsonLd($regionEvents, $base, $dir, $region['name'] . ' borvidék eseményei') ?? [],
    [breadcrumbJsonLd([
        'Főoldal'    => $base . $dir . '/',
        'Borvidékek' => $base . $dir . '/borvidekek',
        $region['name'] => $canonicalUrl,
    ])]
);

// Tény-sáv
$facts = [[(string) count($regionEvents), 'Közelgő esemény']];
if (!empty($ri['grapes'])) { $facts[] = [$ri['grapes'], 'Fő szőlőfajta']; }
if (!empty($ri['wines']))  { $facts[] = [$ri['wines'], 'Jellemző borok']; }

require __DIR__ . '/partials/header.php';
?>
  <article class="rv">
    <div class="rv-hero<?= $hasImg ? '' : ' rv-hero--art' ?>">
      <div class="rv-hero__photo">
        <?php if ($hasImg): ?>
          <img src="<?= h($regImg) ?>" alt="<?= h($region['image_alt'] ?: ($region['name'] . ' borvidék')) ?>">
        <?php else: ?>
          <svg viewBox="0 0 1200 520" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
              <linearGradient id="rvsky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f4dca6"/><stop offset="0.55" stop-color="#e7b779"/><stop offset="1" stop-color="#d99a5e"/>
              </linearGradient>
            </defs>
            <rect width="1200" height="520" fill="url(#rvsky)"/>
            <circle cx="915" cy="140" r="120" fill="#fbe9bd" opacity="0.35"/>
            <circle cx="915" cy="140" r="62" fill="#fdefc9"/>
            <path d="M0,300 Q300,258 620,296 T1200,286 L1200,520 L0,520 Z" fill="#c6a96f"/>
            <path d="M0,352 Q360,300 720,347 T1200,336 L1200,520 L0,520 Z" fill="#9e8d55"/>
            <g opacity="0.9">
              <rect x="835" y="300" width="46" height="34" fill="#eaddc0"/>
              <polygon points="831,300 858,282 885,300" fill="#8a5a3a"/>
              <rect x="853" y="314" width="12" height="20" fill="#6b4a30"/>
            </g>
            <path d="M0,414 Q320,360 640,402 T1200,392 L1200,520 L0,520 Z" fill="#6f6a38"/>
            <g fill="none" stroke="#55522c" stroke-width="2.4" opacity="0.5">
              <path d="M0,436 Q320,384 640,424 T1200,414"/><path d="M0,460 Q320,410 640,448 T1200,440"/>
              <path d="M0,486 Q320,438 640,474 T1200,466"/><path d="M0,512 Q320,466 640,500 T1200,494"/>
            </g>
          </svg>
        <?php endif; ?>
      </div>
      <div class="rv-hero__in">
        <nav class="rv-crumb" aria-label="Morzsa">
          <a href="./">Főoldal</a> › <a href="borvidekek">Borvidékek</a> › <span aria-current="page"><?= h($region['name']) ?></span>
        </nav>
        <p class="rv-eyebrow">Magyar borvidék</p>
        <h1><?= h($region['name']) ?> borvidék</h1>
        <p class="rv-lead"><?= h($intro) ?></p>
      </div>
    </div>

    <div class="rv-facts">
      <div class="rv-facts__in">
        <?php foreach ($facts as [$val, $label]): ?>
          <div class="rv-fact"><b><?= h($val) ?></b><span><?= h($label) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rv-body">
      <div class="rv-sectitle"><h2>Közelgő események</h2><span class="rv-rule"></span></div>

      <?php if (!$regionEvents): ?>
        <div class="rv-empty">
          <p>Ehhez a borvidékhez jelenleg nincs meghirdetett esemény.</p>
          <p><a class="btn btn--ghost" href="naptar">Nézd meg a naptárat →</a>
            <a class="btn btn--ghost" href="esemeny-bekuldes">Van esemény? Küldd be →</a></p>
        </div>
      <?php else: ?>
        <div class="event-rows">
          <?php foreach ($regionEvents as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
            <a class="event-row" href="<?= h(eventUrl($e)) ?>">
              <span class="date-block">
                <span class="date-block__day"><?= h(dayNumber($e['start_datetime'])) ?></span>
                <span class="date-block__mon"><?= h(shortMonthUpper($e['start_datetime'])) ?></span>
              </span>
              <span class="event-row__main">
                <span class="event-row__title">
                  <?= h($e['title']) ?>
                  <?php if ((int) $e['is_featured'] === 1): ?><span class="status is-featured">★ Kiemelt</span><?php endif; ?>
                  <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
                  <?php if ((int) $e['is_free'] === 1): ?><span class="status is-free">Ingyenes</span><?php endif; ?>
                </span>
                <span class="event-row__sub">
                  <?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?>
                  <?php if (!empty($e['city'])): ?> · <span class="event-row__loc">📍 <?= h($e['city']) ?></span><?php endif; ?>
                  <?php if (!empty($e['categories'])): ?> · <?= h(implode(', ', categoryNames($e))) ?><?php endif; ?>
                </span>
              </span>
              <span class="event-row__right"><span class="event-row__chev">→</span></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="rv-back"><a href="borvidekek">← Összes borvidék</a></p>
    </div>
  </article>
<?php
require __DIR__ . '/partials/footer.php';
