<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require_admin();

/** MySQL DATETIME → <input type="datetime-local"> érték (Y-m-dTH:i). */
function dtLocal(?string $dt): string
{
    if (empty($dt)) {
        return '';
    }
    try {
        return (new DateTimeImmutable($dt))->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$errors = [];
$saved = (($_GET['mentve'] ?? '') === 'ok');

$pdo = null;
$regions = [];
$categories = [];
try {
    $pdo = db();
    $regions = $pdo->query('SELECT id, name FROM wine_regions ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT id, slug, name FROM categories ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    error_log('admin/edit.php segédadat hiba: ' . $e->getMessage());
}

$event = null;
if ($id > 0 && $pdo) {
    try {
        $event = fetchEventByIdAdmin($pdo, $id);
    } catch (Throwable $e) {
        error_log('admin/edit.php betöltés hiba: ' . $e->getMessage());
    }
}
if (!$event) {
    http_response_code(404);
    echo 'Esemény nem található. <a href="index.php">Vissza</a>';
    exit;
}

// --- Mentés ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Lejárt munkamenet. Töltsd újra az oldalt és próbáld újra.';
    }

    $f = [
        'title'             => trim((string) ($_POST['title'] ?? '')),
        'short_description' => trim((string) ($_POST['short_description'] ?? '')),
        'description'       => trim((string) ($_POST['description'] ?? '')),
        'venue_name'        => trim((string) ($_POST['venue_name'] ?? '')),
        'address'           => trim((string) ($_POST['address'] ?? '')),
        'city'              => trim((string) ($_POST['city'] ?? '')),
        'website_url'       => trim((string) ($_POST['website_url'] ?? '')),
        'ticket_url'        => trim((string) ($_POST['ticket_url'] ?? '')),
        'price_info'        => trim((string) ($_POST['price_info'] ?? '')),
        'image_url'         => trim((string) ($_POST['image_url'] ?? '')),
        'image_alt'         => trim((string) ($_POST['image_alt'] ?? '')),
    ];
    $start    = toMysqlDatetime($_POST['start_datetime'] ?? '');
    $end      = toMysqlDatetime($_POST['end_datetime'] ?? '');
    $regionId = (string) ($_POST['region_id'] ?? '');
    $isFree   = isset($_POST['is_free']) ? 1 : 0;
    $isFeat   = isset($_POST['is_featured']) ? 1 : 0;
    $status   = (string) ($_POST['status'] ?? 'draft');
    $catSlugs = (array) ($_POST['kategoriak'] ?? []);

    if ($f['title'] === '')                                 { $errors[] = 'Az esemény neve kötelező.'; }
    if ($start === null)                                    { $errors[] = 'Érvényes kezdő időpont kötelező.'; }
    if ($end !== null && $start !== null && $end < $start)  { $errors[] = 'A záró időpont nem lehet korábbi a kezdésnél.'; }
    if (!in_array($status, ['draft', 'published', 'cancelled'], true)) { $errors[] = 'Érvénytelen állapot.'; }
    if ($f['website_url'] !== '' && !filter_var($f['website_url'], FILTER_VALIDATE_URL)) { $errors[] = 'A honlap címe nem érvényes URL.'; }
    if ($f['ticket_url'] !== '' && !filter_var($f['ticket_url'], FILTER_VALIDATE_URL))   { $errors[] = 'A jegy-link nem érvényes URL.'; }

    if (!$errors) {
        try {
            $st = $pdo->prepare(
                "UPDATE events SET
                    title = :title, short_description = :short_desc, description = :desc,
                    start_datetime = :start, end_datetime = :end,
                    venue_name = :venue, address = :address, city = :city, region_id = :region_id,
                    website_url = :website, ticket_url = :ticket, is_free = :is_free, price_info = :price,
                    image_url = :image_url, image_alt = :image_alt,
                    is_featured = :is_featured, status = :status
                 WHERE id = :id"
            );
            $st->execute([
                ':title'      => $f['title'],
                ':short_desc' => $f['short_description'] !== '' ? $f['short_description'] : null,
                ':desc'       => $f['description'] !== '' ? $f['description'] : null,
                ':start'      => $start,
                ':end'        => $end,
                ':venue'      => $f['venue_name'] !== '' ? $f['venue_name'] : null,
                ':address'    => $f['address'] !== '' ? $f['address'] : null,
                ':city'       => $f['city'] !== '' ? $f['city'] : null,
                ':region_id'  => $regionId !== '' ? (int) $regionId : null,
                ':website'    => $f['website_url'] !== '' ? $f['website_url'] : null,
                ':ticket'     => $f['ticket_url'] !== '' ? $f['ticket_url'] : null,
                ':is_free'    => $isFree,
                ':price'      => $f['price_info'] !== '' ? $f['price_info'] : null,
                ':image_url'  => $f['image_url'] !== '' ? $f['image_url'] : null,
                ':image_alt'  => $f['image_alt'] !== '' ? $f['image_alt'] : null,
                ':is_featured' => $isFeat,
                ':status'     => $status,
                ':id'         => $id,
            ]);

            // Kategóriák újraírása
            $pdo->prepare('DELETE FROM event_categories WHERE event_id = ?')->execute([$id]);
            if ($catSlugs && $categories) {
                $idBySlug = [];
                foreach ($categories as $c) {
                    $idBySlug[$c['slug']] = (int) $c['id'];
                }
                $link = $pdo->prepare('INSERT IGNORE INTO event_categories (event_id, category_id) VALUES (?, ?)');
                foreach ($catSlugs as $cs) {
                    if (isset($idBySlug[$cs])) {
                        $link->execute([$id, $idBySlug[$cs]]);
                    }
                }
            }

            header('Location: edit.php?id=' . $id . '&mentve=ok');
            exit;
        } catch (Throwable $e) {
            error_log('admin/edit.php mentés hiba: ' . $e->getMessage());
            $errors[] = 'Hiba történt a mentés során.';
        }
    }

    // Hibánál a beküldött értékek visszatöltése a megjelenítéshez
    $event = array_merge($event, $f, [
        'start_datetime' => $start ?? $event['start_datetime'],
        'end_datetime'   => $end,
        'region_id'      => $regionId !== '' ? (int) $regionId : null,
        'is_free'        => $isFree,
        'is_featured'    => $isFeat,
        'status'         => $status,
        'cat_slugs'      => $catSlugs,
    ]);
}

$csrf = admin_csrf_token();
$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
$catSel = (array) ($event['cat_slugs'] ?? []);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Szerkesztés — admin</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span><a href="index.php">← Vissza a listához</a> &nbsp;·&nbsp; <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1>Esemény szerkesztése</h1>

    <?php if ($saved): ?>
      <div class="admin-msg">Mentve. ✓</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="admin-error">
        <strong>Nem menthető:</strong>
        <ul><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form class="submit-form" method="post" action="edit.php?id=<?= $id ?>">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">

      <h2 class="form-section-title">Állapot</h2>
      <div class="form-grid">
        <div class="field">
          <label for="status">Állapot</label>
          <select id="status" name="status">
            <?php foreach (['draft' => 'Beérkezett (draft)', 'published' => 'Közzétett', 'cancelled' => 'Lemondott'] as $k => $lbl): ?>
              <option value="<?= $k ?>"<?= ($event['status'] ?? 'draft') === $k ? ' selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <label class="check"><input type="checkbox" name="is_featured" value="1"<?= (int) ($event['is_featured'] ?? 0) === 1 ? ' checked' : '' ?>> Kiemelt esemény</label>
        </div>
      </div>

      <h2 class="form-section-title">Az esemény</h2>
      <div class="form-grid">
        <div class="field field--full">
          <label for="title">Esemény neve <span class="req">*</span></label>
          <input type="text" id="title" name="title" required maxlength="255" value="<?= h($event['title'] ?? '') ?>">
          <span class="field__hint">URL-slug (nem módosul): <code><?= h($event['slug'] ?? '') ?></code></span>
        </div>
        <div class="field">
          <label for="start_datetime">Kezdés <span class="req">*</span></label>
          <input type="datetime-local" id="start_datetime" name="start_datetime" required value="<?= h(dtLocal($event['start_datetime'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="end_datetime">Befejezés</label>
          <input type="datetime-local" id="end_datetime" name="end_datetime" value="<?= h(dtLocal($event['end_datetime'] ?? '')) ?>">
        </div>
        <div class="field field--full">
          <label for="short_description">Rövid leírás</label>
          <input type="text" id="short_description" name="short_description" maxlength="500" value="<?= h($event['short_description'] ?? '') ?>">
        </div>
        <div class="field field--full">
          <label for="description">Részletes leírás</label>
          <textarea id="description" name="description"><?= h($event['description'] ?? '') ?></textarea>
        </div>
        <div class="field field--full">
          <label>Kategóriák</label>
          <div class="checks">
            <?php foreach ($categories as $c): ?>
              <label class="check">
                <input type="checkbox" name="kategoriak[]" value="<?= h($c['slug']) ?>"<?= in_array($c['slug'], $catSel, true) ? ' checked' : '' ?>>
                <?= h($c['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <h2 class="form-section-title">Helyszín</h2>
      <div class="form-grid">
        <div class="field">
          <label for="venue_name">Helyszín neve</label>
          <input type="text" id="venue_name" name="venue_name" maxlength="255" value="<?= h($event['venue_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="city">Település</label>
          <input type="text" id="city" name="city" maxlength="120" value="<?= h($event['city'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="address">Cím</label>
          <input type="text" id="address" name="address" maxlength="255" value="<?= h($event['address'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="region_id">Borvidék</label>
          <select id="region_id" name="region_id">
            <option value="">— Nincs —</option>
            <?php foreach ($regions as $rg): ?>
              <option value="<?= (int) $rg['id'] ?>"<?= (string) ($event['region_id'] ?? '') === (string) $rg['id'] ? ' selected' : '' ?>><?= h($rg['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <h2 class="form-section-title">Jegy, ár, kép</h2>
      <div class="form-grid">
        <div class="field">
          <label for="website_url">Hivatalos honlap</label>
          <input type="url" id="website_url" name="website_url" placeholder="https://" value="<?= h($event['website_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="ticket_url">Jegy-link</label>
          <input type="url" id="ticket_url" name="ticket_url" placeholder="https://" value="<?= h($event['ticket_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="price_info">Ár-információ</label>
          <input type="text" id="price_info" name="price_info" maxlength="255" value="<?= h($event['price_info'] ?? '') ?>">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <label class="check"><input type="checkbox" name="is_free" value="1"<?= (int) ($event['is_free'] ?? 0) === 1 ? ' checked' : '' ?>> Ingyenes</label>
        </div>
        <div class="field">
          <label for="image_url">Kép URL</label>
          <input type="text" id="image_url" name="image_url" maxlength="500" value="<?= h($event['image_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="image_alt">Kép alt-szöveg</label>
          <input type="text" id="image_alt" name="image_alt" maxlength="255" value="<?= h($event['image_alt'] ?? '') ?>">
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Mentés</button>
        <a class="form-note" href="index.php">Mégse</a>
      </div>
    </form>
  </main>
</body>
</html>
