<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél feliratkozás (POST). PRG: feldolgozás után visszairányít.
// Új feliratkozónak üdvözlő e-mailt küldünk (a levél el nem küldése nem hiúsítja
// meg a feliratkozást — a cím ilyenkor is bekerül a listába).

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';
require __DIR__ . '/lib/subscribers.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/newsletter.php';

$email = trim((string) ($_POST['email'] ?? ''));
$ok = false;

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $pdo = db();
        ensureSubscribersTable($pdo);

        // Leiratkozó token már feliratkozáskor készül (a levelek linkjéhez).
        $email = mb_strtolower($email, 'UTF-8');
        $token = bin2hex(random_bytes(16));
        $st = $pdo->prepare('INSERT IGNORE INTO subscribers (email, unsubscribe_token) VALUES (:e, :t)');
        $st->execute([':e' => $email, ':t' => $token]);
        $ok = true;

        // Üdvözlő e-mail — csak ÚJ feliratkozónak (ismételt feliratkozásra nem spammelünk).
        if ($st->rowCount() > 0) {
            $base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
            $unsub = $base . $dir . '/leiratkozas.php?t=' . $token;

            // Kiemelt események (max 3) + a következő 31 nap eseményei (max 20,
            // kiemelt-duplikáció nélkül). Ha a lekérdezés hibázik, a levél
            // listák nélkül, de akkor is kimegy.
            $featured = [];
            $monthItems = [];
            $more = 0;
            try {
                $toItem = static fn(array $e): array => [
                    'title' => (string) $e['title'],
                    'url'   => eventUrl($e, $base, $dir),
                    'date'  => formatDateRange($e['start_datetime'], $e['end_datetime']),
                    'city'  => (string) ($e['city'] ?? ''),
                    'free'  => (int) $e['is_free'] === 1,
                    'day'   => dayNumber($e['start_datetime']),
                    'mon'   => shortMonthUpper($e['start_datetime']),
                ];

                $upcoming = fetchUpcomingEvents($pdo);
                $feat = array_slice(array_values(array_filter(
                    $upcoming,
                    static fn(array $e): bool => (int) $e['is_featured'] === 1
                )), 0, 3);
                $featIds = array_map(static fn(array $e): int => (int) $e['id'], $feat);
                $featured = array_map($toItem, $feat);

                $from = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                $to   = (new DateTimeImmutable('today +31 days'))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                $month = array_values(array_filter(
                    fetchEventsBetween($pdo, $from, $to),
                    static fn(array $e): bool => !in_array((int) $e['id'], $featIds, true)
                ));
                $more = max(0, count($month) - 20);
                $monthItems = array_map($toItem, array_slice($month, 0, 20));
            } catch (Throwable $e) {
                error_log('newsletter.php: esemény-lista a levélhez nem elérhető: ' . $e->getMessage());
            }

            $sent = sendMailHtml(
                $email,
                'Üdv a holborozzak.hu hírlevelén! 🍷',
                nlWelcomeHtml($base . $dir . '/esemenyek', $unsub, $featured, $monthItems, $more),
                ['List-Unsubscribe' => '<' . $unsub . '>']
            );
            if (!$sent) {
                error_log('newsletter.php: az üdvözlő e-mail küldése nem sikerült: ' . $email);
            }
        }
    } catch (Throwable $e) {
        error_log('newsletter.php DB hiba: ' . $e->getMessage());
        $ok = false;
    }
}

header('Location: ./?hirlevel=' . ($ok ? 'ok' : 'hiba') . '#hirlevel');
exit;
