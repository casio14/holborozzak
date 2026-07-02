<?php
declare(strict_types=1);

// holborozzak.hu — hírlevél feliratkozás (POST). PRG: feldolgozás után visszairányít.
// Új feliratkozónak üdvözlő e-mailt küldünk (a levél el nem küldése nem hiúsítja
// meg a feliratkozást — a cím ilyenkor is bekerül a listába).

require __DIR__ . '/db.php';
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
            $sent = sendMailHtml(
                $email,
                'Üdv a holborozzak.hu hírlevelén! 🍷',
                nlWelcomeHtml($base . $dir . '/', $unsub),
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
