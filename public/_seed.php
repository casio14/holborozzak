<?php
declare(strict_types=1);

// IDEIGLENES, tokennel védett seed-importáló. Egyszeri használat után TÖRÖLNI!
// Lefuttatja a public/seed.sql-t a szerver meglévő DB-kapcsolatával (INSERT IGNORE).

header('Content-Type: text/plain; charset=utf-8');

$TOKEN = 'b0r0zz4k-seed-7Qx2026';
if (($_GET['key'] ?? '') !== $TOKEN) {
    http_response_code(403);
    exit("Tiltva.\n");
}

require __DIR__ . '/db.php';

$sql = @file_get_contents(__DIR__ . '/seed.sql');
if ($sql === false) {
    http_response_code(500);
    exit("Nincs seed.sql.\n");
}

// Komment- és üres sorok eltávolítása, majd utasításokra bontás (';' mentén)
$lines = preg_split('/\r?\n/', $sql);
$clean = [];
foreach ($lines as $ln) {
    if (preg_match('/^\s*--/', $ln)) { continue; }
    $clean[] = $ln;
}
$stmts = array_filter(array_map('trim', explode(';', implode("\n", $clean))));

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    exit("DB hiba: nincs kapcsolat.\n");
}

$ran = 0;
$errors = [];
foreach ($stmts as $stmt) {
    if ($stmt === '') { continue; }
    try {
        $pdo->exec($stmt);
        $ran++;
    } catch (Throwable $e) {
        $errors[] = substr($stmt, 0, 50) . ' … -> ' . $e->getMessage();
    }
}

$events = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$links  = (int) $pdo->query('SELECT COUNT(*) FROM event_categories')->fetchColumn();

echo "Lefuttatott utasitasok: {$ran}\n";
echo "events sorok: {$events}\n";
echo "event_categories sorok: {$links}\n";
echo $errors ? ("HIBAK:\n" . implode("\n", $errors) . "\n") : "OK\n";
