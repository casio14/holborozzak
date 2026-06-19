<?php
declare(strict_types=1);

// Ideiglenes egészség-ellenőrző: igazolja, hogy a DB-kapcsolat él.
// TODO: élesítés előtt törölni vagy jelszóval/IP-vel védeni.

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/db.php';

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    echo "db: ok\n";

    // Ha a séma is be van importálva, mutassuk a borvidékek számát (várt: 22).
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM wine_regions')->fetchColumn();
        echo "wine_regions: {$n}\n";
        echo "schema: ok\n";
    } catch (Throwable $e) {
        echo "schema: hianyzik (futtasd le a db/schema.sql-t)\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    // Rövid kategória a hibakereséshez (nem dump-oljuk a teljes hibát/jelszót).
    $msg = $e->getMessage();
    if (stripos($msg, 'config.php') !== false) {
        echo "db: error - config hianyzik\n";
    } elseif (stripos($msg, 'Access denied') !== false) {
        echo "db: error - rossz jelszo/felhasznalo (DB_PASSWORD secret?)\n";
    } elseif (stripos($msg, 'Unknown database') !== false) {
        echo "db: error - nincs ilyen adatbazis\n";
    } else {
        echo "db: error - kapcsolat sikertelen\n";
    }
    error_log('health.php DB hiba: ' . $msg);
}
