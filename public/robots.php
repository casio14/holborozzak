<?php
declare(strict_types=1);

// holborozzak.hu — robots.txt DINAMIKUSAN kiszolgálva (a statikus fájl helyett),
// mert a hoszting-proxy (RHProxy) anti-bot rétege a statikus /robots.txt-nél a
// Googlebot user-agentet átirányítás-hurokba lökte (Search Console „redirect error").
// A tartalom megegyezik a korábbi statikus robots.txt-vel; a .htaccess a
// /robots.txt kérést ide irányítja (mint a sitemap.xml → sitemap.php).

header('Content-Type: text/plain; charset=utf-8');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'holborozzak.hu';

echo <<<TXT
# holborozzak.hu — robots.txt
User-agent: *
Allow: /
# Kimenő kattintás-átirányító (tracker)
Disallow: /go.php
# Admin felület
Disallow: /admin/
# Naptár-letöltés (.ics)
Disallow: /ics.php
# Gyűjtő fogadó végpont (API)
Disallow: /collect-ingest.php
# Hírlevél-leiratkozás (tokenes linkek)
Disallow: /leiratkozas.php
# Hírlevél-küldő végpont (API)
Disallow: /newsletter-send.php

# --- AI keresők / asszisztensek kifejezetten engedélyezve ---
User-agent: GPTBot
Allow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Claude-Web
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: CCBot
Allow: /

Sitemap: {$scheme}://{$host}/sitemap.xml

TXT;
