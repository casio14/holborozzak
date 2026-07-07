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
$isNew = ($id <= 0);               // id nélkül = új esemény felvétele
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
if (!$isNew && $pdo) {
    try {
        $event = fetchEventByIdAdmin($pdo, $id);
    } catch (Throwable $e) {
        error_log('admin/edit.php betöltés hiba: ' . $e->getMessage());
    }
    if (!$event) {
        http_response_code(404);
        echo 'Esemény nem található. <a href="index.php">Vissza</a>';
        exit;
    }
}
if ($isNew) {
    $event = ['status' => 'draft']; // üres űrlap, alapértelmezett állapot
}

// --- Mentés ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Lejárt munkamenet. Töltsd újra az oldalt és próbáld újra.';
    }

    $f = [
        'title'             => trim((string) ($_POST['title'] ?? '')),
        'short_description' => trim((string) ($_POST['short_description'] ?? '')),
        'description'       => sanitizeRichHtml((string) ($_POST['description'] ?? '')),
        'venue_name'        => trim((string) ($_POST['venue_name'] ?? '')),
        'address'           => trim((string) ($_POST['address'] ?? '')),
        'city'              => trim((string) ($_POST['city'] ?? '')),
        'website_url'       => trim((string) ($_POST['website_url'] ?? '')),
        'facebook_url'      => trim((string) ($_POST['facebook_url'] ?? '')),
        'ticket_url'        => trim((string) ($_POST['ticket_url'] ?? '')),
        'price_info'        => trim((string) ($_POST['price_info'] ?? '')),
        'image_url'         => trim((string) ($_POST['image_url'] ?? '')),
        'image_alt'         => trim((string) ($_POST['image_alt'] ?? '')),
    ];
    if (trim(strip_tags($f['description'])) === '') { $f['description'] = ''; } // csak formázás → üres
    $start    = toMysqlDatetime($_POST['start_datetime'] ?? '');
    $end      = toMysqlDatetime($_POST['end_datetime'] ?? '');
    $regionId = (string) ($_POST['region_id'] ?? '');
    $lat      = trim((string) ($_POST['latitude'] ?? ''));
    $lng      = trim((string) ($_POST['longitude'] ?? ''));
    $isFree   = isset($_POST['is_free']) ? 1 : 0;
    $isFeat   = isset($_POST['is_featured']) ? 1 : 0;
    $status   = (string) ($_POST['status'] ?? 'draft');
    $catSlugs = (array) ($_POST['kategoriak'] ?? []);

    if ($f['title'] === '')                                 { $errors[] = 'Az esemény neve kötelező.'; }
    if ($start === null)                                    { $errors[] = 'Érvényes kezdő időpont kötelező.'; }
    if ($end !== null && $start !== null && $end < $start)  { $errors[] = 'A záró időpont nem lehet korábbi a kezdésnél.'; }
    if (!in_array($status, ['draft', 'published', 'cancelled'], true)) { $errors[] = 'Érvénytelen állapot.'; }
    if ($f['website_url'] !== '' && !filter_var($f['website_url'], FILTER_VALIDATE_URL)) { $errors[] = 'A honlap címe nem érvényes URL.'; }
    if ($f['facebook_url'] !== '' && !filter_var($f['facebook_url'], FILTER_VALIDATE_URL)) { $errors[] = 'A Facebook-link nem érvényes URL.'; }
    if ($f['ticket_url'] !== '' && !filter_var($f['ticket_url'], FILTER_VALIDATE_URL))   { $errors[] = 'A jegy-link nem érvényes URL.'; }
    if ($lat !== '' && !is_numeric($lat)) { $errors[] = 'A szélesség (latitude) szám legyen.'; }
    if ($lng !== '' && !is_numeric($lng)) { $errors[] = 'A hosszúság (longitude) szám legyen.'; }

    if (!$errors) {
        try {
            $params = [
                ':title'      => $f['title'],
                ':short_desc' => $f['short_description'] !== '' ? $f['short_description'] : null,
                ':desc'       => $f['description'] !== '' ? $f['description'] : null,
                ':start'      => $start,
                ':end'        => $end,
                ':venue'      => $f['venue_name'] !== '' ? $f['venue_name'] : null,
                ':address'    => $f['address'] !== '' ? $f['address'] : null,
                ':city'       => $f['city'] !== '' ? $f['city'] : null,
                ':region_id'  => $regionId !== '' ? (int) $regionId : null,
                ':lat'        => $lat !== '' ? $lat : null,
                ':lng'        => $lng !== '' ? $lng : null,
                ':website'    => $f['website_url'] !== '' ? $f['website_url'] : null,
                ':facebook'   => $f['facebook_url'] !== '' ? $f['facebook_url'] : null,
                ':ticket'     => $f['ticket_url'] !== '' ? $f['ticket_url'] : null,
                ':is_free'    => $isFree,
                ':price'      => $f['price_info'] !== '' ? $f['price_info'] : null,
                ':image_url'  => $f['image_url'] !== '' ? $f['image_url'] : null,
                ':image_alt'  => $f['image_alt'] !== '' ? $f['image_alt'] : null,
                ':is_featured' => $isFeat,
                ':status'     => $status,
            ];

            if ($isNew) {
                // Új esemény: ütközésmentes slug a címből + évszám, majd beszúrás.
                $year = (new DateTimeImmutable((string) $start))->format('Y');
                $params[':slug'] = uniqueEventSlug($pdo, slugify($f['title']) . '-' . $year);
                $pdo->prepare(
                    "INSERT INTO events
                        (slug, title, short_description, description, start_datetime, end_datetime,
                         venue_name, address, city, region_id, latitude, longitude,
                         website_url, facebook_url, ticket_url, is_free, price_info,
                         image_url, image_alt, is_featured, status)
                     VALUES
                        (:slug, :title, :short_desc, :desc, :start, :end,
                         :venue, :address, :city, :region_id, :lat, :lng,
                         :website, :facebook, :ticket, :is_free, :price,
                         :image_url, :image_alt, :is_featured, :status)"
                )->execute($params);
                $id = (int) $pdo->lastInsertId();
            } else {
                $params[':id'] = $id;
                $pdo->prepare(
                    "UPDATE events SET
                        title = :title, short_description = :short_desc, description = :desc,
                        start_datetime = :start, end_datetime = :end,
                        venue_name = :venue, address = :address, city = :city, region_id = :region_id,
                        latitude = :lat, longitude = :lng,
                        website_url = :website, facebook_url = :facebook, ticket_url = :ticket,
                        is_free = :is_free, price_info = :price,
                        image_url = :image_url, image_alt = :image_alt,
                        is_featured = :is_featured, status = :status
                     WHERE id = :id"
                )->execute($params);
            }

            // Kategóriák (újra)írása
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
        'start_datetime' => $start ?? ($event['start_datetime'] ?? null),
        'end_datetime'   => $end,
        'region_id'      => $regionId !== '' ? (int) $regionId : null,
        'latitude'       => $lat,
        'longitude'      => $lng,
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
  <title><?= $isNew ? 'Új esemény' : 'Szerkesztés' ?> — admin</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <div class="admin-bar">
    <span class="admin-bar__title">holborozzak.hu — admin</span>
    <span class="admin-bar__links"><a href="index.php">← Vissza a listához</a> <a href="logout.php">Kilépés</a></span>
  </div>

  <main class="admin-main">
    <h1><?= $isNew ? 'Új esemény hozzáadása' : 'Esemény szerkesztése' ?></h1>

    <?php if ($saved): ?>
      <div class="admin-msg">Mentve. ✓</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="admin-error">
        <strong>Nem menthető:</strong>
        <ul><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form class="submit-form" method="post" action="edit.php<?= $isNew ? '' : '?id=' . $id ?>">
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
          <span class="field__hint"><?php if ($isNew): ?>Az URL-slug mentéskor a címből generálódik.<?php else: ?>URL-slug (nem módosul): <code><?= h($event['slug'] ?? '') ?></code><?php endif; ?></span>
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
          <?php $descHtml = renderDescription($event['description'] ?? ''); ?>
          <label for="description">Részletes leírás</label>
          <div class="rte" data-rte="description" hidden>
            <div class="rte__toolbar" role="toolbar" aria-label="Formázás">
              <button type="button" class="rte__btn" data-cmd="bold" title="Félkövér"><b>F</b></button>
              <button type="button" class="rte__btn" data-cmd="italic" title="Dőlt"><i>D</i></button>
              <button type="button" class="rte__btn" data-cmd="underline" title="Aláhúzott"><u>A</u></button>
              <span class="rte__sep"></span>
              <button type="button" class="rte__btn" data-cmd="insertUnorderedList" title="Felsorolás">•&nbsp;—</button>
              <button type="button" class="rte__btn" data-cmd="insertOrderedList" title="Számozott lista">1.</button>
              <span class="rte__sep"></span>
              <button type="button" class="rte__btn" data-cmd="createLink" title="Link beszúrása">🔗</button>
              <button type="button" class="rte__btn" data-cmd="removeFormat" title="Formázás törlése">⌫</button>
            </div>
            <div class="rte__area" contenteditable="true" role="textbox" aria-multiline="true"
                 aria-label="Részletes leírás" data-placeholder="Írd le a program részleteit — formázhatod is…"><?= $descHtml ?></div>
          </div>
          <textarea id="description" name="description" class="rte__source" rows="9"><?= h($descHtml) ?></textarea>
          <span class="field__hint">Formázhatod: félkövér, dőlt, aláhúzott, listák, link.</span>
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
        <div class="field">
          <label for="latitude">Szélesség (latitude)</label>
          <input type="text" id="latitude" name="latitude" value="<?= h((string) ($event['latitude'] ?? '')) ?>" placeholder="pl. 47.4979">
          <span class="field__hint">A térképhez. Google Mapsen: jobb klikk → a koordináták.</span>
        </div>
        <div class="field">
          <label for="longitude">Hosszúság (longitude)</label>
          <input type="text" id="longitude" name="longitude" value="<?= h((string) ($event['longitude'] ?? '')) ?>" placeholder="pl. 19.0402">
        </div>
      </div>

      <h2 class="form-section-title">Jegy, ár, kép</h2>
      <div class="form-grid">
        <div class="field">
          <label for="website_url">Hivatalos honlap</label>
          <input type="url" id="website_url" name="website_url" placeholder="https://" value="<?= h($event['website_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="facebook_url">Facebook-esemény</label>
          <input type="url" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/events/…" value="<?= h($event['facebook_url'] ?? '') ?>">
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
        <button type="submit" class="btn btn--primary"><?= $isNew ? 'Esemény létrehozása' : 'Mentés' ?></button>
        <a class="form-note" href="index.php">Mégse</a>
      </div>
    </form>
  </main>

  <script>
    // Progresszív rich-text szerkesztő: JS nélkül a sima <textarea> marad használatban.
    (function () {
      var wraps = document.querySelectorAll('[data-rte]');
      Array.prototype.forEach.call(wraps, function (wrap) {
        var area = wrap.querySelector('.rte__area');
        var source = document.getElementById(wrap.getAttribute('data-rte'));
        if (!area || !source) { return; }
        source.hidden = true;
        wrap.hidden = false;
        function sync() { source.value = area.innerHTML; }
        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);
        Array.prototype.forEach.call(wrap.querySelectorAll('.rte__btn'), function (btn) {
          btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
          btn.addEventListener('click', function () {
            var cmd = btn.getAttribute('data-cmd');
            area.focus();
            try {
              document.execCommand('styleWithCSS', false, false);
              if (cmd === 'createLink') {
                var url = window.prompt('Add meg a link címét (https://…):', 'https://');
                if (url) { document.execCommand('createLink', false, url); }
              } else {
                document.execCommand(cmd, false, null);
              }
            } catch (err) { /* nem támogatott parancs — kihagyjuk */ }
            sync();
          });
        });
        var form = wrap.closest('form');
        if (form) { form.addEventListener('submit', sync); }
        sync();
      });
    })();
  </script>
</body>
</html>
