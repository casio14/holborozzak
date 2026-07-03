<?php
/**
 * Közös fejléc + dokumentum nyitás, beépített SEO / AI-kereső (GEO) fundamentummal.
 *
 * Beillesztés előtt opcionálisan állítható:
 *   $pageTitle, $pageDescription, $canonicalUrl, $ogType, $ogImage, $robots, $jsonLd
 */
$siteName = 'holborozzak.hu';

$pageTitle = $pageTitle
    ?? 'holborozzak.hu — Magyarország borhoz köthető eseményei';
$pageDescription = $pageDescription
    ?? 'Magyarország borhoz köthető eseményei egy helyen — borfesztiválok, bornapok, szüreti rendezvények, közelgő és ingyenes programok térképpel.';

// Abszolút URL-ek a tényleges kérésből (így bármely domainen helyes: ideiglenes is, végleges is)
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'holborozzak.hu';
$baseUrl = $scheme . '://' . $host;
$path    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?'); // query nélkül a canonicalhoz

$canonicalUrl = $canonicalUrl ?? ($baseUrl . $path);
$ogType  = $ogType  ?? 'website';
$robots  = $robots  ?? 'index,follow';
$activeNav = $activeNav ?? ''; // melyik menüpont aktív: esemenyek | naptar | terkep

// Az oldal gyökere (ahol a PHP fájlok élnek) — a SCRIPT_NAME-ből, így a szép
// URL-es (/esemeny/<slug>) kéréseknél is a valós könyvtárat adja. A <base> tag
// erre mutat, ezért a relatív linkek/assetek a mélyebb útvonalú oldalakon is jók.
$baseHref = $baseUrl . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';

// Alapértelmezett megosztókép (oldalanként felülírható az oldal $ogImage-ével)
$ogImage = $ogImage ?? ($baseHref . 'assets/hero.jpg');

// --- Strukturált adat: alap WebSite + Organization minden oldalon ---
$defaultJsonLd = [
    [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        'name'        => $siteName,
        'url'         => $baseUrl . '/',
        'description' => $pageDescription,
        'inLanguage'  => 'hu-HU',
    ],
    [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => $siteName,
        'url'      => $baseUrl . '/',
    ],
];
$jsonLd = isset($jsonLd) ? array_merge($defaultJsonLd, $jsonLd) : $defaultJsonLd;
$jsonLdFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// Cache-busting: a CSS verziója a fájl módosítási ideje → CSS-változás után
// a böngésző automatikusan friss stíluslapot tölt (nincs több ragadós cache).
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
$jsVer  = @filemtime(__DIR__ . '/../assets/app.js') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES) ?>">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
  <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
  <meta name="theme-color" content="#722f37">

  <!-- Open Graph -->
  <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES) ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES) ?>">
  <meta property="og:locale" content="hu_HU">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<?php if (!empty($ogImage)): ?>
  <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
<?php endif; ?>

  <!-- Twitter Card -->
  <meta name="twitter:card" content="<?= !empty($ogImage) ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
<?php if (!empty($ogImage)): ?>
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
<?php endif; ?>

  <link rel="stylesheet" href="assets/style.css?v=<?= $cssVer ?>">
  <script src="assets/app.js?v=<?= $jsVer ?>" defer></script>

  <!-- Strukturált adat (Schema.org JSON-LD) — SEO + AI-kereső -->
<?php foreach ($jsonLd as $block): ?>
  <script type="application/ld+json">
<?= json_encode($block, $jsonLdFlags) ?>
  </script>
<?php endforeach; ?>
<?= $headExtra ?? '' ?>
</head>
<body>
  <header class="site-header">
    <div class="site-header__inner">
      <input type="checkbox" id="nav-toggle" class="nav-toggle">
      <a class="brand" href="./" aria-label="holborozzak.hu — kezdőlap">
        <svg class="brand__icon" width="44" height="44" viewBox="0 0 32 32" aria-hidden="true">
          <path d="M16 7c1.8-2.6 5.2-2.8 6.6-1.8-.6 2.6-3.2 3.8-5.4 3.8" fill="#5a6b3b"/>
          <g fill="currentColor">
            <circle cx="16" cy="12" r="2.6"/>
            <circle cx="12" cy="16" r="2.6"/>
            <circle cx="20" cy="16" r="2.6"/>
            <circle cx="16" cy="16.5" r="2.6"/>
            <circle cx="13.5" cy="20.5" r="2.6"/>
            <circle cx="18.5" cy="20.5" r="2.6"/>
            <circle cx="16" cy="24.5" r="2.6"/>
          </g>
        </svg>
        <span class="brand__text">
          <span class="brand__name">hol<span class="brand__accent">borozzak</span>.hu</span>
          <span class="brand__tag">Borrendezvények egy helyen</span>
        </span>
      </a>

      <nav class="site-nav" aria-label="Fő navigáció">
        <!-- TODO: a Naptár és Térkép céloldal a következő inkrementumokban készül el -->
        <a href="esemenyek.php"<?= $activeNav === 'esemenyek' ? ' class="is-active" aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <circle cx="3.6" cy="6" r="1.3" fill="currentColor" stroke="none"/>
            <circle cx="3.6" cy="12" r="1.3" fill="currentColor" stroke="none"/>
            <circle cx="3.6" cy="18" r="1.3" fill="currentColor" stroke="none"/>
          </svg>
          Események
        </a>
        <a href="naptar.php"<?= $activeNav === 'naptar' ? ' class="is-active" aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4.5" width="18" height="16" rx="2"/>
            <line x1="3" y1="9" x2="21" y2="9"/>
            <line x1="8" y1="2.5" x2="8" y2="6"/>
            <line x1="16" y1="2.5" x2="16" y2="6"/>
          </svg>
          Naptár
        </a>
        <a href="terkep.php"<?= $activeNav === 'terkep' ? ' class="is-active" aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="3 6.5 9 3.5 15 6.5 21 3.5 21 17.5 15 20.5 9 17.5 3 20.5"/>
            <line x1="9" y1="3.5" x2="9" y2="17.5"/>
            <line x1="15" y1="6.5" x2="15" y2="20.5"/>
          </svg>
          Térkép
        </a>
        <a class="site-nav__cta" href="esemeny-bekuldes.php">Esemény beküldése</a>
      </nav>

      <div class="site-header__actions">
        <a class="site-cta" href="esemeny-bekuldes.php">Esemény beküldése</a>
        <label class="nav-burger" for="nav-toggle" aria-label="Menü">
          <svg class="nav-burger__open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
          <svg class="nav-burger__close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>
          </svg>
        </label>
      </div>
    </div>
  </header>
  <main>
