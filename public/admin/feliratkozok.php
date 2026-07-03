<?php
declare(strict_types=1);

// holborozzak.hu — admin: hírlevél-feliratkozók (lista, CSV-export, törlés).

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require __DIR__ . '/../lib/subscribers.php';
require_admin();

$msg = (string) ($_GET['msg'] ?? '');
$csrf = admin_csrf_token();

$rows = [];
$err = false;
try {
    $pdo = db();
    ensureSubscribersTable($pdo);

    // Törlés (POST + CSRF, PRG) — pl. GDPR-es kérés kézi teljesítéséhez.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (admin_csrf_check($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'delete') {
            $st = $pdo->prepare('DELETE FROM subscribers WHERE id = :id');
            $st->execute([':id' => (int) ($_POST['id'] ?? 0)]);
            header('Location: feliratkozok.php?msg=ok');
        } else {
            header('Location: feliratkozok.php?msg=hiba');
        }
        exit;
    }

    $rows = $pdo->query('SELECT id, email, created_at FROM subscribers ORDER BY created_at DESC, id DESC')->fetchAll();

    // CSV-export (Excel-barát: UTF-8 BOM + pontosvessző)
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="holborozzak-feliratkozok-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['email', 'feliratkozas_ideje'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [$r['email'], $r['created_at']], ';');
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    error_log('admin feliratkozok DB hiba: ' . $e->getMessage());
    $err = true;
}

$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Feliratkozók — admin · holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span><a href="index.php">Események</a> &nbsp;·&nbsp; <a href="jeloltek.php">Jelöltek</a> &nbsp;·&nbsp; <a href="statisztika.php">Statisztika</a> &nbsp;·&nbsp; <a href="../" target="_blank">Oldal megtekintése ↗</a> &nbsp;·&nbsp; <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1>Hírlevél-feliratkozók (<?= count($rows) ?>)</h1>

    <?php if ($msg === 'ok'): ?>
      <div class="admin-msg">A művelet sikeres. ✓</div>
    <?php elseif ($msg === 'hiba'): ?>
      <div class="admin-error">A művelet nem sikerült (lejárt munkamenet vagy hiba). Próbáld újra.</div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="admin-error">Nem sikerült betölteni a feliratkozókat. Nézd meg a naplót.</div>
    <?php elseif (!$rows): ?>
      <div class="admin-empty">Még nincs feliratkozó.</div>
    <?php else: ?>
      <p><a class="admin-link" href="feliratkozok.php?export=csv">⬇ Exportálás CSV-be (<?= count($rows) ?> cím)</a></p>

      <table class="admin-table">
        <thead>
          <tr>
            <th>E-mail</th>
            <th>Feliratkozás ideje</th>
            <th>Műveletek</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
              <td><?= h(date('Y. m. d. H:i', strtotime((string) $r['created_at']))) ?></td>
              <td class="admin-actions-cell">
                <form method="post" action="feliratkozok.php" class="admin-actform" onsubmit="return confirm('Biztosan törlöd ezt a feliratkozót?')">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button class="admin-btn admin-btn--danger" type="submit">Törlés</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>
