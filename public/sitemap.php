<?php
declare(strict_types=1);

// holborozzak.hu — dinamikus sitemap (csak közzétett események).
// Az URL-eket a tényleges kérésből építi → bármely domainen helyes.

require __DIR__ . '/db.php';

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'holborozzak.hu';
$dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$root   = $scheme . '://' . $host . $dir;   // pl. https://holborozzak.hu  vagy  .../borozzak

$u = static fn(string $path): string => $root . '/' . ltrim($path, '/');

$urls = [
    ['loc' => $root . '/',            'changefreq' => 'daily',   'priority' => '1.0'],
    ['loc' => $u('esemenyek.php'),    'changefreq' => 'daily',   'priority' => '0.9'],
    ['loc' => $u('naptar.php'),       'changefreq' => 'weekly',  'priority' => '0.7'],
    ['loc' => $u('terkep.php'),       'changefreq' => 'weekly',  'priority' => '0.7'],
    ['loc' => $u('esemeny-bekuldes.php'), 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => $u('impresszum.php'),   'changefreq' => 'yearly',  'priority' => '0.2'],
    ['loc' => $u('aszf.php'),         'changefreq' => 'yearly',  'priority' => '0.2'],
    ['loc' => $u('adatvedelem.php'),  'changefreq' => 'yearly',  'priority' => '0.2'],
];

try {
    $st = db()->query("SELECT slug, updated_at FROM events WHERE status = 'published' ORDER BY start_datetime DESC");
    foreach ($st as $e) {
        $urls[] = [
            'loc'        => $u('esemeny/' . rawurlencode((string) $e['slug'])),
            'lastmod'    => date('Y-m-d', strtotime((string) $e['updated_at']) ?: time()),
            'changefreq' => 'weekly',
            'priority'   => '0.8',
        ];
    }
} catch (Throwable $e) {
    error_log('sitemap.php DB hiba: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    if (!empty($url['lastmod'])) {
        echo '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
