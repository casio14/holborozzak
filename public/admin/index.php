<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require_admin();

$TABS = ['draft' => 'Beérkezett', 'published' => 'Közzétett', 'cancelled' => 'Lemondott'];
$tab = (string) ($_GET['tab'] ?? 'draft');
if (!isset($TABS[$tab])) {
    $tab = 'draft';
}
$msg = (string) ($_GET['msg'] ?? '');
$csrf = admin_csrf_token();

$rows = [];
$counts = ['draft' => 0, 'published' => 0, 'cancelled' => 0];
try {
    $pdo = db();
    $order = $tab === 'published' ? 'start_datetime DESC' : 'created_at DESC';
    $st = $pdo->prepare(
        "SELECT id, title, city, start_datetime, submitter_name, submitter_email, created_at, is_featured, status
         FROM events WHERE status = :s ORDER BY {$order}"
    );
    $st->execute([':s' => $tab]);
    $rows = $st->fetchAll();
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM events GROUP BY status") as $r) {
        $counts[$r['status']] = (int) $r['c'];
    }
} catch (Throwable $e) {
    error_log('admin index DB hiba: ' . $e->getMessage());
}

/** Egy művelet-gomb (POST + CSRF). */
function actBtn(string $action, int $id, string $tab, string $csrf, string $label, string $cls = 'admin-btn', bool $confirm = false): string
{
    $oc = $confirm ? ' onsubmit="return confirm(\'Biztosan?\')"' : '';
    return '<form method="post" action="action.php" class="admin-actform"' . $oc . '>'
        . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="tab" value="' . h($tab) . '">'
        . '<button class="' . h($cls) . '" type="submit">' . h($label) . '</button></form>';
}

$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Admin — holborozzak.hu</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span><a href="jeloltek.php">Jelöltek</a> &nbsp;·&nbsp; <a href="../" target="_blank">Oldal megtekintése ↗</a> &nbsp;·&nbsp; <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1>Események kezelése</h1>

    <?php if ($msg === 'ok'): ?>
      <div class="admin-msg">A művelet sikeres. ✓</div>
    <?php elseif ($msg === 'hiba'): ?>
      <div class="admin-error">A művelet nem sikerült (lejárt munkamenet vagy hiba). Próbáld újra.</div>
    <?php endif; ?>

    <nav class="admin-tabs">
      <?php foreach ($TABS as $key => $label): ?>
        <a class="admin-tab<?= $tab === $key ? ' is-active' : '' ?>" href="index.php?tab=<?= h($key) ?>">
          <?= h($label) ?> (<?= (int) $counts[$key] ?>)
        </a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$rows): ?>
      <div class="admin-empty">Ebben a nézetben nincs esemény.</div>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Esemény</th>
            <th>Helyszín</th>
            <th>Időpont</th>
            <th>Beküldő</th>
            <th>Műveletek</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
            <tr>
              <td>
                <?php if ((int) $r['is_featured'] === 1): ?><span class="admin-star" title="Kiemelt">★</span> <?php endif; ?>
                <strong><?= h($r['title']) ?></strong>
              </td>
              <td><?= h($r['city'] ?: '—') ?></td>
              <td><?= h(formatDateRange($r['start_datetime'], null)) ?></td>
              <td>
                <?= h($r['submitter_name'] ?: '—') ?>
                <?php if (!empty($r['submitter_email'])): ?><br><a href="mailto:<?= h($r['submitter_email']) ?>"><?= h($r['submitter_email']) ?></a><?php endif; ?>
              </td>
              <td class="admin-actions-cell">
                <a class="admin-link" href="edit.php?id=<?= $id ?>">Szerkesztés</a>
                <?php if ($tab === 'draft'): ?>
                  <?= actBtn('publish', $id, $tab, $csrf, 'Jóváhagyás', 'admin-btn admin-btn--go') ?>
                  <?= actBtn('cancel', $id, $tab, $csrf, 'Elutasítás', 'admin-btn admin-btn--danger', true) ?>
                <?php elseif ($tab === 'published'): ?>
                  <?= actBtn('feature', $id, $tab, $csrf, (int) $r['is_featured'] === 1 ? 'Kiemelés le' : 'Kiemel') ?>
                  <?= actBtn('draft', $id, $tab, $csrf, 'Visszavonás') ?>
                  <?= actBtn('cancel', $id, $tab, $csrf, 'Lemondás', 'admin-btn admin-btn--danger', true) ?>
                <?php else: /* cancelled */ ?>
                  <?= actBtn('draft', $id, $tab, $csrf, 'Visszaállítás') ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>
