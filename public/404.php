<?php
declare(strict_types=1);

// holborozzak.hu — 404 oldal (ErrorDocument). Önálló, abszolút URL-ekkel,
// hogy bármilyen mélységű hibás kérésnél is helyesen töltsön be.

http_response_code(404);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'holborozzak.hu';
$base   = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$asset  = $base . '/assets/style.css';
$home   = $base . '/';
$events = $base . '/esemenyek.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title>404 — az oldal nem található | holborozzak.hu</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($asset, ENT_QUOTES) ?>">
  <style>
    .nf { max-width: 640px; margin: 0 auto; padding: 5rem 1.25rem; text-align: center; }
    .nf h1 { color: var(--wine-900); font-size: clamp(2rem, 6vw, 3rem); margin: 0 0 .5rem; }
    .nf p { color: var(--muted); font-size: 1.1rem; margin: 0 0 1.75rem; }
    .nf__actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
  </style>
</head>
<body>
  <main class="nf">
    <h1>404 🍷</h1>
    <p>Ezt az oldalt nem találjuk — lehet, hogy elavult a link, vagy elgépelés történt.</p>
    <div class="nf__actions">
      <a class="btn btn--primary" href="<?= htmlspecialchars($home, ENT_QUOTES) ?>">Vissza a főoldalra</a>
      <a class="btn btn--ghost" href="<?= htmlspecialchars($events, ENT_QUOTES) ?>">Összes esemény</a>
    </div>
  </main>
</body>
</html>
