<?php
declare(strict_types=1);

// Admin módosító műveletek: státuszváltás + kiemelés-kapcsoló. POST + CSRF, PRG.

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require_admin();

$tab = (string) ($_POST['tab'] ?? 'draft');
if (!in_array($tab, ['draft', 'published', 'cancelled'], true)) {
    $tab = 'draft';
}
$back = 'index.php?tab=' . rawurlencode($tab);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_check($_POST['csrf'] ?? null)) {
    header('Location: ' . $back . '&msg=hiba');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

// Művelet → SQL (whitelist)
$sqlByAction = [
    'publish' => "UPDATE events SET status = 'published' WHERE id = ?",
    'cancel'  => "UPDATE events SET status = 'cancelled' WHERE id = ?",
    'draft'   => "UPDATE events SET status = 'draft' WHERE id = ?",
    'feature' => "UPDATE events SET is_featured = 1 - is_featured WHERE id = ?",
];

if ($id <= 0 || !isset($sqlByAction[$action])) {
    header('Location: ' . $back . '&msg=hiba');
    exit;
}

try {
    $st = db()->prepare($sqlByAction[$action]);
    $st->execute([$id]);
    header('Location: ' . $back . '&msg=ok');
    exit;
} catch (Throwable $e) {
    error_log('admin/action.php hiba: ' . $e->getMessage());
    header('Location: ' . $back . '&msg=hiba');
    exit;
}
