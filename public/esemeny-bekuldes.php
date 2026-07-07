<?php
declare(strict_types=1);

// holborozzak.hu — esemény beküldő űrlap.
// GET: űrlap megjelenítése. POST: validálás + mentés draft eseményként (PRG sikernél).
// A beküldött esemény NEM jelenik meg azonnal: 'draft' státusszal kerül be, jóváhagyásra vár.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

$errors = [];
$old = [];
$done = (($_GET['bekuldve'] ?? '') === 'ok');

// Borvidékek + kategóriák a legördülőkhöz / jelölőnégyzetekhez
$regions = [];
$categories = [];
try {
    $pdo = db();
    $regions = $pdo->query('SELECT id, name FROM wine_regions ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT id, slug, name FROM categories ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    error_log('esemeny-bekuldes.php segédadat hiba: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Spam-védelem: honeypot + idő-csapda. Gyanús esetben CSENDES „siker":
    //     nem mentünk, de a botnak úgy tűnik, sikerült — így nem tanul a blokkból. ---
    $hpFilled = trim((string) ($_POST['url'] ?? '')) !== '';        // rejtett csali-mező
    $formTs   = (int) ($_POST['form_ts'] ?? 0);                      // űrlap-megjelenítés ideje
    $tooFast  = $formTs <= 0 || (time() - $formTs) < 3;             // <3 mp = gyanúsan gyors
    if ($hpFilled || $tooFast) {
        error_log('esemeny-bekuldes.php spam-gyanu (hp=' . ($hpFilled ? '1' : '0')
            . ', tooFast=' . ($tooFast ? '1' : '0') . ')');
        header('Location: esemeny-bekuldes?bekuldve=ok');
        exit;
    }

    $old = $_POST;

    $title      = trim((string) ($_POST['title'] ?? ''));
    $shortDesc  = trim((string) ($_POST['short_description'] ?? ''));
    $desc       = sanitizeRichHtml((string) ($_POST['description'] ?? ''));
    if (trim(strip_tags($desc)) === '') { $desc = ''; } // csak formázás, szöveg nélkül → üres
    $startRaw   = (string) ($_POST['start_datetime'] ?? '');
    $endRaw     = (string) ($_POST['end_datetime'] ?? '');
    $venue      = trim((string) ($_POST['venue_name'] ?? ''));
    $address    = trim((string) ($_POST['address'] ?? ''));
    $city       = trim((string) ($_POST['city'] ?? ''));
    $regionId   = (string) ($_POST['region_id'] ?? '');
    $website    = trim((string) ($_POST['website_url'] ?? ''));
    $facebook   = trim((string) ($_POST['facebook_url'] ?? ''));
    $ticket     = trim((string) ($_POST['ticket_url'] ?? ''));
    $isFree     = isset($_POST['is_free']) ? 1 : 0;
    $priceInfo  = trim((string) ($_POST['price_info'] ?? ''));
    $catSlugs   = (array) ($_POST['kategoriak'] ?? []);
    $sName      = trim((string) ($_POST['submitter_name'] ?? ''));
    $sEmail     = trim((string) ($_POST['submitter_email'] ?? ''));

    $start = toMysqlDatetime($startRaw);
    $end   = toMysqlDatetime($endRaw);

    // Validálás
    if ($title === '')                                    { $errors[] = 'Az esemény neve kötelező.'; }
    if ($start === null)                                  { $errors[] = 'Az érvényes kezdő időpont kötelező.'; }
    if ($end !== null && $start !== null && $end < $start) { $errors[] = 'A záró időpont nem lehet korábbi a kezdésnél.'; }
    if ($city === '')                                     { $errors[] = 'A település (város) megadása kötelező.'; }
    if (!filter_var($sEmail, FILTER_VALIDATE_EMAIL))      { $errors[] = 'Érvényes kapcsolattartó e-mail cím szükséges.'; }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) { $errors[] = 'A honlap címe nem érvényes URL.'; }
    if ($facebook !== '' && !filter_var($facebook, FILTER_VALIDATE_URL)) { $errors[] = 'A Facebook-link nem érvényes URL.'; }
    if ($ticket !== '' && !filter_var($ticket, FILTER_VALIDATE_URL))   { $errors[] = 'A jegyvásárlás linkje nem érvényes URL.'; }

    if (!$errors) {
        try {
            $pdo = db();
            $year = (new DateTimeImmutable($start))->format('Y');
            $slug = uniqueEventSlug($pdo, slugify($title) . '-' . $year);

            $st = $pdo->prepare(
                "INSERT INTO events
                   (slug, title, short_description, description, start_datetime, end_datetime,
                    venue_name, address, city, region_id, website_url, facebook_url, ticket_url,
                    is_free, price_info, status, submitter_name, submitter_email)
                 VALUES
                   (:slug, :title, :short_desc, :desc, :start, :end,
                    :venue, :address, :city, :region_id, :website, :facebook, :ticket,
                    :is_free, :price, 'draft', :sname, :semail)"
            );
            $st->execute([
                ':slug'       => $slug,
                ':title'      => $title,
                ':short_desc' => $shortDesc !== '' ? $shortDesc : null,
                ':desc'       => $desc !== '' ? $desc : null,
                ':start'      => $start,
                ':end'        => $end,
                ':venue'      => $venue !== '' ? $venue : null,
                ':address'    => $address !== '' ? $address : null,
                ':city'       => $city,
                ':region_id'  => $regionId !== '' ? (int) $regionId : null,
                ':website'    => $website !== '' ? $website : null,
                ':facebook'   => $facebook !== '' ? $facebook : null,
                ':ticket'     => $ticket !== '' ? $ticket : null,
                ':is_free'    => $isFree,
                ':price'      => $priceInfo !== '' ? $priceInfo : null,
                ':sname'      => $sName !== '' ? $sName : null,
                ':semail'     => $sEmail,
            ]);

            // Kategóriák (slug → id) a kapcsolótáblába
            if ($catSlugs && $categories) {
                $idBySlug = [];
                foreach ($categories as $c) {
                    $idBySlug[$c['slug']] = (int) $c['id'];
                }
                $eventId = (int) $pdo->lastInsertId();
                $link = $pdo->prepare('INSERT IGNORE INTO event_categories (event_id, category_id) VALUES (?, ?)');
                foreach ($catSlugs as $cs) {
                    if (isset($idBySlug[$cs])) {
                        $link->execute([$eventId, $idBySlug[$cs]]);
                    }
                }
            }

            header('Location: esemeny-bekuldes?bekuldve=ok');
            exit;
        } catch (Throwable $e) {
            error_log('esemeny-bekuldes.php mentés hiba: ' . $e->getMessage());
            $errors[] = 'Váratlan hiba történt a mentés során. Kérlek próbáld újra később.';
        }
    }
}

$pageTitle = 'Esemény beküldése — holborozzak.hu';
$pageDescription = 'Küldd be ingyenesen a borrendezvényedet a holborozzak.hu-ra: '
    . 'borfesztivál, kóstoló, szüreti program. Jóváhagyás után megjelenik a listában, térképen és naptárban.';
$robots = 'noindex,follow'; // utility oldal — ne kerüljön a keresőbe
require __DIR__ . '/partials/header.php';
?>
  <div class="container">
  <?php if ($done): ?>
    <div class="form-success">
      <h1>Köszönjük a beküldést! 🍷</h1>
      <p>Az eseményt megkaptuk, és hamarosan átnézzük. Jóváhagyás után megjelenik a
        listában, a térképen és a naptárban is.</p>
      <p>Ha kiemelnéd az eseményt, válaszolj a visszaigazoló e-mailre, vagy írj az
        <a href="mailto:info@holborozzak.hu?subject=Esem%C3%A9ny%20kiemel%C3%A9se">info@holborozzak.hu</a> címre.</p>
      <p><a class="btn btn--primary" href="./">Vissza a főoldalra</a></p>
    </div>
  <?php else: ?>
    <div class="form-intro">
      <h1>Esemény beküldése</h1>
      <p>Töltsd ki az alábbi űrlapot, és beküldjük jóváhagyásra. A megjelenés ingyenes —
        jóváhagyás után az esemény látszik a listában, a térképen és a naptárban is.
        A <span class="req">*</span>-gal jelölt mezők kötelezők.</p>
    </div>

    <?php if ($errors): ?>
      <div class="form-error" role="alert">
        <strong>Az űrlap nem küldhető el:</strong>
        <ul>
          <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form class="submit-form" method="post" action="esemeny-bekuldes" novalidate>

      <!-- Spam-védelem: idő-csapda + honeypot (ne töltsd ki / ne nevezd át) -->
      <input type="hidden" name="form_ts" value="<?= time() ?>">
      <div class="hp-field" aria-hidden="true">
        <label for="url">Ezt a mezőt hagyd üresen</label>
        <input type="text" id="url" name="url" tabindex="-1" autocomplete="off">
      </div>

      <h2 class="form-section-title">Az esemény</h2>
      <div class="form-grid">
        <div class="field field--full">
          <label for="title">Esemény neve <span class="req">*</span></label>
          <input type="text" id="title" name="title" required maxlength="255" value="<?= h($old['title'] ?? '') ?>">
        </div>

        <div class="field">
          <label for="start_datetime">Kezdés <span class="req">*</span></label>
          <input type="datetime-local" id="start_datetime" name="start_datetime" required value="<?= h($old['start_datetime'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="end_datetime">Befejezés</label>
          <input type="datetime-local" id="end_datetime" name="end_datetime" value="<?= h($old['end_datetime'] ?? '') ?>">
          <span class="field__hint">Több napos eseménynél töltsd ki.</span>
        </div>

        <div class="field field--full">
          <label for="short_description">Rövid leírás</label>
          <input type="text" id="short_description" name="short_description" maxlength="500" value="<?= h($old['short_description'] ?? '') ?>">
          <span class="field__hint">Egy mondat, ami a lista-kártyán jelenik meg.</span>
        </div>

        <div class="field field--full">
          <?php $oldDesc = sanitizeRichHtml((string) ($old['description'] ?? '')); ?>
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
                 aria-label="Részletes leírás" data-placeholder="Írd le a program részleteit — formázhatod is…"><?= $oldDesc ?></div>
          </div>
          <textarea id="description" name="description" class="rte__source" rows="9"><?= h($oldDesc) ?></textarea>
          <span class="field__hint">Formázhatod a szöveget: félkövér, dőlt, aláhúzott, listák, link.</span>
        </div>

        <div class="field field--full">
          <label>Kategóriák</label>
          <div class="checks">
            <?php
            $oldCats = (array) ($old['kategoriak'] ?? []);
            foreach ($categories as $c):
            ?>
              <label class="check">
                <input type="checkbox" name="kategoriak[]" value="<?= h($c['slug']) ?>"<?= in_array($c['slug'], $oldCats, true) ? ' checked' : '' ?>>
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
          <input type="text" id="venue_name" name="venue_name" maxlength="255" value="<?= h($old['venue_name'] ?? '') ?>">
          <span class="field__hint">Pl. „Budai Vár".</span>
        </div>
        <div class="field">
          <label for="city">Település <span class="req">*</span></label>
          <input type="text" id="city" name="city" required maxlength="120" value="<?= h($old['city'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="address">Cím</label>
          <input type="text" id="address" name="address" maxlength="255" value="<?= h($old['address'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="region_id">Borvidék</label>
          <select id="region_id" name="region_id">
            <option value="">— Válassz —</option>
            <?php foreach ($regions as $r): ?>
              <option value="<?= (int) $r['id'] ?>"<?= ((string) ($old['region_id'] ?? '') === (string) $r['id']) ? ' selected' : '' ?>><?= h($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <h2 class="form-section-title">Jegy és ár</h2>
      <div class="form-grid">
        <div class="field">
          <label for="website_url">Hivatalos honlap</label>
          <input type="url" id="website_url" name="website_url" placeholder="https://" value="<?= h($old['website_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="facebook_url">Facebook-esemény</label>
          <input type="url" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/events/…" value="<?= h($old['facebook_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="ticket_url">Jegyvásárlás linkje</label>
          <input type="url" id="ticket_url" name="ticket_url" placeholder="https://" value="<?= h($old['ticket_url'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="price_info">Ár-információ</label>
          <input type="text" id="price_info" name="price_info" maxlength="255" placeholder="Pl. Belépő 3 000 Ft-tól" value="<?= h($old['price_info'] ?? '') ?>">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <label class="check">
            <input type="checkbox" name="is_free" value="1"<?= !empty($old['is_free']) ? ' checked' : '' ?>>
            Ingyenes az esemény
          </label>
        </div>
      </div>

      <h2 class="form-section-title">Kapcsolattartó (nem jelenik meg nyilvánosan)</h2>
      <div class="form-grid">
        <div class="field">
          <label for="submitter_name">Neved</label>
          <input type="text" id="submitter_name" name="submitter_name" maxlength="120" value="<?= h($old['submitter_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="submitter_email">E-mail cím <span class="req">*</span></label>
          <input type="email" id="submitter_email" name="submitter_email" required value="<?= h($old['submitter_email'] ?? '') ?>">
          <span class="field__hint">Ide írunk vissza a jóváhagyásról vagy ha kérdésünk van.</span>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Esemény beküldése →</button>
        <span class="form-note">A beküldéssel elfogadod az <a href="adatvedelem">adatkezelési tájékoztatót</a>.</span>
      </div>
    </form>
  <?php endif; ?>
  </div>

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
          // a lenyomás ne vegye el a fókuszt a szerkesztőterületről
          btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
          btn.addEventListener('click', function () {
            var cmd = btn.getAttribute('data-cmd');
            area.focus();
            try {
              // tageket adjon (pl. <b>), ne inline style-t — a fertőtlenítő így megtartja
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
<?php
require __DIR__ . '/partials/footer.php';
