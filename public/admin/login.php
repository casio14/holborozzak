<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

// Már belépve → irány a vezérlőpult
if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_session_start();
    $user = (string) ($_POST['user'] ?? '');
    $pass = (string) ($_POST['pass'] ?? '');
    $cfg  = admin_config();

    $okUser = !empty($cfg['user']) && hash_equals((string) $cfg['user'], $user);
    $okPass = !empty($cfg['pass_hash']) && password_verify($pass, (string) $cfg['pass_hash']);

    if ($okUser && $okPass) {
        session_regenerate_id(true);          // session fixation ellen
        $_SESSION['admin_ok'] = true;
        $_SESSION['admin_login_at'] = time();
        // „Ne mérj engem" süti: a saját (admin) forgalom ne hígítsa a statisztikát.
        // Tartós (1 év), a publikus oldalakon is látszik; a naplózók kihagyják.
        setcookie('hb_notrack', '1', [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: index.php');
        exit;
    }

    usleep(400000);                            // kis késleltetés a brute-force lassítására
    $error = 'Hibás felhasználónév vagy jelszó.';
}

$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Admin belépés — holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <form class="admin-login" method="post" action="login.php">
    <h1>Admin belépés</h1>
    <?php if ($error !== ''): ?>
      <div class="admin-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <label for="user">Felhasználónév</label>
    <input type="text" id="user" name="user" autocomplete="username" required autofocus>
    <label for="pass">Jelszó</label>
    <input type="password" id="pass" name="pass" autocomplete="current-password" required>
    <button type="submit" class="btn btn--primary">Belépés</button>
  </form>
</body>
</html>
