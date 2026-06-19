<?php
declare(strict_types=1);

/**
 * Adatbázis-kapcsolat (PDO, MySQL).
 *
 * A hozzáférési adatokat a config.php szolgáltatja, amit:
 *   - éles környezetben a CI generál a DB_PASSWORD secretből (deploy közben),
 *   - lokálisan te hozol létre a config.example.php alapján.
 *
 * Használat:
 *   require __DIR__ . '/db.php';
 *   $pdo = db();
 *   $stmt = $pdo->query('SELECT ...');
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Hiányzó config.php — másold le a config.example.php-t, vagy a CI generálja éles környezetben.');
    }

    $config = require $configFile;
    $db = $config['db'] ?? [];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? 'localhost',
        (int) ($db['port'] ?? 3306),
        $db['name'] ?? ''
    );

    // PDOException dobódik kapcsolódási hibánál — a hívó kezeli/logolja.
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
