<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél-küldő végpont (token-védett, a GitHub Actions cron hívja).
//
// A workflow HETENTE hív, de a 13 napos szerveroldali védelem miatt ténylegesen
// KÉTHETENTE megy ki levél (?force=1 kézi futtatásnál megkerüli). A levél a
// következő 3 hét közzétett eseményeit tartalmazza; ha nincs esemény, nem küldünk.
// A küldésekről a newsletter_log tábla vezet naplót.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';
require __DIR__ . '/lib/subscribers.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/newsletter.php';

header('Content-Type: application/json; charset=utf-8');

// --- Token-ellenőrzés (mint a collect-ingest.php-nál) ---
$cfgFile = __DIR__ . '/config.php';
$cfg = is_file($cfgFile) ? require $cfgFile : [];
$expected = (string) ($cfg['newsletter_token'] ?? '');
$fromHeader = (string) ($_SERVER['HTTP_X_NEWSLETTER_TOKEN'] ?? '');
$fromPost   = (string) ($_POST['token'] ?? '');
$given = $fromHeader !== '' ? $fromHeader : $fromPost;
// A whitespace-t levágjuk: a secretbe másoláskor könnyen kerül sortörés/szóköz.
$given = trim($given);
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'forbidden',
        'hint'  => $expected === '' ? 'config newsletter_token ures (deploy kell)' : 'token nem egyezik',
        // Veszélytelen diagnosztika (értéket nem árul el): honnan jött token és milyen hosszú.
        'diag'  => [
            'kapott_fejlecben' => $fromHeader !== '',
            'kapott_postban'   => $fromPost !== '',
            'kapott_hossz'     => strlen($given),
            'vart_hossz'       => strlen($expected),
        ],
    ]);
    exit;
}

$force = (string) ($_GET['force'] ?? $_POST['force'] ?? '') === '1';

try {
    $pdo = db();
    ensureSubscribersTable($pdo);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_log (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sent_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            recipients   INT NOT NULL DEFAULT 0,
            events_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Kéthetenkénti védelem: ha 13 napon belül már ment levél, most nem küldünk.
    if (!$force) {
        $last = $pdo->query('SELECT MAX(sent_at) FROM newsletter_log')->fetchColumn();
        if ($last && (time() - strtotime((string) $last)) < 13 * 86400) {
            echo json_encode(['skipped' => 'too_soon', 'last_sent' => $last]);
            exit;
        }
    }

    // Közelgő események: a következő 3 hét, legfeljebb 12 tétel.
    $from = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');
    $to   = (new DateTimeImmutable('today +21 days'))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    $events = array_slice(fetchEventsBetween($pdo, $from, $to), 0, 12);
    if (!$events) {
        echo json_encode(['skipped' => 'no_events']);
        exit;
    }

    $base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

    $items = [];
    foreach ($events as $e) {
        $items[] = [
            'title'    => (string) $e['title'],
            'url'      => eventUrl($e, $base, $dir),
            'date'     => formatDateRange($e['start_datetime'], $e['end_datetime']),
            'city'     => (string) ($e['city'] ?? ''),
            'free'     => (int) $e['is_free'] === 1,
            'day'      => dayNumber($e['start_datetime']),
            'mon'      => shortMonthUpper($e['start_datetime']),
            'featured' => (int) $e['is_featured'] === 1,
        ];
    }

    // Feliratkozók; hiányzó leiratkozó tokenek pótlása (régi feliratkozások).
    $subs = $pdo->query('SELECT id, email, unsubscribe_token FROM subscribers')->fetchAll();
    $fill = $pdo->prepare('UPDATE subscribers SET unsubscribe_token = :t WHERE id = :id');
    foreach ($subs as &$s) {
        if (empty($s['unsubscribe_token'])) {
            $s['unsubscribe_token'] = bin2hex(random_bytes(16));
            $fill->execute([':t' => $s['unsubscribe_token'], ':id' => (int) $s['id']]);
        }
    }
    unset($s);

    $subject = 'Közelgő borrendezvények — ' . date('Y. m. d.') . ' 🍷';
    $listUrl = $base . $dir . '/esemenyek';
    $sent = 0;
    $failed = 0;
    foreach ($subs as $s) {
        $unsub = $base . $dir . '/leiratkozas.php?t=' . $s['unsubscribe_token'];
        $okMail = sendMailHtml(
            (string) $s['email'],
            $subject,
            nlDigestHtml($items, $listUrl, $unsub),
            ['List-Unsubscribe' => '<' . $unsub . '>']
        );
        $okMail ? $sent++ : $failed++;
    }

    $log = $pdo->prepare('INSERT INTO newsletter_log (recipients, events_count) VALUES (:r, :e)');
    $log->execute([':r' => $sent, ':e' => count($items)]);

    echo json_encode(['sent' => $sent, 'failed' => $failed, 'events' => count($items)]);
} catch (Throwable $e) {
    error_log('newsletter-send.php hiba: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal']);
}
