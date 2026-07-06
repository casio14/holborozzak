<?php
declare(strict_types=1);

// holborozzak.hu — nyitóoldal (landing).

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

logAiReferral(); // AI-asszisztensből (utm_source/referrer) érkező látogató naplózása
logSearchReferral(); // Keresőmotorból (referrer) érkező látogató naplózása

$pageTitle = 'holborozzak.hu — Magyarország borrendezvényei egy helyen';
$pageDescription = 'Fedezd fel Magyarország legjobb bor-eseményeit: fesztiválok, kóstolók '
    . 'és pincelátogatások Tokajtól Villányig — egy helyen, mindig naprakészen.';
$activeNav = '';

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$events = [];
try {
    $events = fetchUpcomingEvents(db());
} catch (Throwable $e) {
    error_log('index.php DB hiba: ' . $e->getMessage());
}

$featured = array_values(array_filter($events, static fn($e) => (int) $e['is_featured'] === 1));
$preview  = array_slice($events, 0, 6);

$hirlevel = $_GET['hirlevel'] ?? '';

$ld = eventsItemListJsonLd($events, $base, $dir);
if ($ld) {
    $jsonLd = $ld;
}

require __DIR__ . '/partials/header.php';
?>
  <section class="hero">
    <div class="hero__inner">
      <p class="hero__eyebrow">Magyarország borrendezvényei</p>
      <h1>Fedezd fel az ország legjobb bor-eseményeit</h1>
      <p class="hero__lead">
        Fesztiválok, kóstolók és pincelátogatások Tokajtól Villányig
        — egy helyen, mindig naprakészen.
      </p>
      <form class="hero__search" role="search" method="get" action="esemenyek">
        <input id="hero-kereso" type="search" name="q"
               placeholder="Keresés helyszín, borvidék vagy esemény szerint…"
               aria-label="Keresés helyszín, borvidék vagy esemény szerint">
        <button type="submit">Keresés</button>
      </form>
      <div class="hero__stats">
        <?php if ($events): ?>
          <span class="hero__stat"><b><?= count($events) ?></b><span>közelgő esemény</span></span>
        <?php endif; ?>
        <span class="hero__stat"><b>22</b><span>borvidék</span></span>
        <span class="hero__stat"><b>Naponta</b><span>frissítve</span></span>
      </div>
    </div>
  </section>

  <div class="container landing-panel">

    <?php if ($featured): ?>
    <section class="events-section">
      <div class="events-section__head">
        <h2>Kiemelt események</h2>
        <a class="events-section__more" href="esemenyek">Összes esemény →</a>
      </div>
      <div class="events-grid">
        <?php foreach ($featured as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
          <article class="event-card">
            <a class="event-card__media" href="<?= h(eventUrl($e)) ?>">
              <img src="<?= h(eventImage($e)) ?>" alt="<?= h($e['image_alt'] ?: $e['title']) ?>" loading="lazy">
              <span class="event-card__badge">Kiemelt</span>
            </a>
            <div class="event-card__body">
              <p class="event-card__date">
                <time datetime="<?= h(isoDate($e['start_datetime'])) ?>"><?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?></time>
                <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
              </p>
              <h3 class="event-card__title"><a href="<?= h(eventUrl($e)) ?>"><?= h($e['title']) ?></a></h3>
              <p class="event-card__meta">📍 <?= h(trim(($e['venue_name'] ? $e['venue_name'] . ', ' : '') . $e['city'])) ?></p>
              <div class="event-card__tags">
                <?php foreach ($e['categories'] as $cat): ?><span class="tag"><?= h($cat['name']) ?></span><?php endforeach; ?>
                <?php if ((int) $e['is_free'] === 1): ?><span class="tag tag--free">Ingyenes</span><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($preview): ?>
    <section class="events-section">
      <div class="events-section__head">
        <h2>Közelgő események</h2>
        <a class="events-section__more" href="esemenyek">Összes esemény →</a>
      </div>
      <div class="events-list">
        <?php foreach ($preview as $e): $st = eventStatus($e['start_datetime'], $e['end_datetime']); ?>
          <a class="event-row" href="<?= h(eventUrl($e)) ?>">
            <span class="date-block">
              <span class="date-block__day"><?= h(dayNumber($e['start_datetime'])) ?></span>
              <span class="date-block__mon"><?= h(shortMonthUpper($e['start_datetime'])) ?></span>
            </span>
            <span class="event-row__main">
              <span class="event-row__title">
                <?= h($e['title']) ?>
                <?php if ($st): ?><span class="status <?= h($st['class']) ?>"><?= h($st['label']) ?></span><?php endif; ?>
                <?php if ((int) $e['is_free'] === 1): ?><span class="status is-free">Ingyenes</span><?php endif; ?>
              </span>
              <span class="event-row__sub">
                <?= h(formatDateRange($e['start_datetime'], $e['end_datetime'])) ?>
                <?php if (!empty($e['city'])): ?> · <span class="event-row__loc">📍 <?= h($e['city']) ?></span><?php endif; ?>
                <?php if (!empty($e['categories'])): ?> · <?= h(implode(', ', categoryNames($e))) ?><?php endif; ?>
              </span>
            </span>
            <span class="event-row__right">
              <span class="event-row__chev">→</span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="events-section">
      <p class="browse-eyebrow">Felfedezés</p>
      <div class="events-section__head"><h2>Böngéssz másképp</h2></div>
      <div class="browse-grid">
        <a class="browse-tile" href="esemenyek">
          <div class="browse-tile__head">
            <span class="browse-tile__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                <circle cx="3.6" cy="6" r="1.3" fill="currentColor" stroke="none"/><circle cx="3.6" cy="12" r="1.3" fill="currentColor" stroke="none"/><circle cx="3.6" cy="18" r="1.3" fill="currentColor" stroke="none"/>
              </svg>
            </span>
            <h3 class="browse-tile__title">Összes esemény</h3>
          </div>
          <p class="browse-tile__desc">A teljes lista — szűrés borvidék, kategória és időpont szerint.</p>
          <span class="browse-tile__arrow" aria-hidden="true">→</span>
        </a>
        <a class="browse-tile" href="terkep">
          <div class="browse-tile__head">
            <span class="browse-tile__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="3 6.5 9 3.5 15 6.5 21 3.5 21 17.5 15 20.5 9 17.5 3 20.5"/>
                <line x1="9" y1="3.5" x2="9" y2="17.5"/><line x1="15" y1="6.5" x2="15" y2="20.5"/>
              </svg>
            </span>
            <h3 class="browse-tile__title">Térkép</h3>
          </div>
          <p class="browse-tile__desc">Nézd meg, milyen rendezvények vannak a közeledben.</p>
          <span class="browse-tile__arrow" aria-hidden="true">→</span>
        </a>
        <a class="browse-tile" href="naptar">
          <div class="browse-tile__head">
            <span class="browse-tile__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4.5" width="18" height="16" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
                <line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/>
              </svg>
            </span>
            <h3 class="browse-tile__title">Naptár</h3>
          </div>
          <p class="browse-tile__desc">Böngészés dátum szerint, naptáros nézetben.</p>
          <span class="browse-tile__arrow" aria-hidden="true">→</span>
        </a>
      </div>
    </section>

    <!-- Szervezőknek CTA -->
    <section class="cta-band cta-band--compact">
      <div class="cta-band__txt">
        <p class="cta-band__eyebrow">Szervezőknek</p>
        <h2>Rendezel borrendezvényt?</h2>
        <p>Küldd be ingyenesen, vagy vedd fel velünk a kapcsolatot, ha ki szeretnéd emelni.</p>
      </div>
      <div class="cta-band__actions">
        <a class="btn btn--gold" href="esemeny-bekuldes">Esemény beküldése →</a>
        <a class="btn btn--ghost-light" href="mailto:info@holborozzak.hu?subject=Esem%C3%A9ny%20kiemel%C3%A9se">Kiemelés iránt érdeklődöm</a>
      </div>
    </section>

    <!-- Hírlevél -->
    <section class="news-band" id="hirlevel">
      <div class="news-band__inner">
        <span class="news-band__eyebrow">🍷 Hírlevél</span>
        <h2>Ne maradj le egy borrendezvényről se</h2>
        <p class="news-band__lead">Iratkozz fel, és időben értesítünk a közelgő fesztiválokról, kóstolókról és szüreti programokról.</p>
        <?php if ($hirlevel === 'ok'): ?>
          <p class="news-band__msg">Köszönjük a feliratkozást! 🍷</p>
        <?php elseif ($hirlevel === 'hiba'): ?>
          <p class="news-band__msg news-band__msg--err">Hiba történt. Kérlek ellenőrizd az e-mail címet, és próbáld újra.</p>
        <?php endif; ?>
        <form class="news-form" method="post" action="newsletter.php">
          <input type="email" name="email" required placeholder="email@cim.hu" aria-label="E-mail cím">
          <button type="submit" class="btn btn--primary">Feliratkozom</button>
        </form>
        <ul class="news-perks">
          <li>📨 Kéthetente egy levél</li>
          <li>🚫 Spam nélkül</li>
          <li>✅ Bármikor leiratkozhatsz</li>
        </ul>
        <p class="news-band__note">A feliratkozással elfogadod az <a href="adatvedelem">adatkezelési tájékoztatót</a>. · <a href="leiratkozas.php">Leiratkozás</a></p>
      </div>
    </section>

  </div>
<?php
require __DIR__ . '/partials/footer.php';
