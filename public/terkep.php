<?php
declare(strict_types=1);

// holborozzak.hu — Eseménytérkép: a borrendezvények interaktív térképen.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

logAiReferral(); // AI-asszisztensből (utm_source/referrer) érkező látogató naplózása
logSearchReferral(); // Keresőmotorból (referrer) érkező látogató naplózása

$pageTitle = 'Eseménytérkép — Magyarország borrendezvényei a térképen | holborozzak.hu';
$pageDescription = 'Magyarország borrendezvényei egy interaktív térképen — találd meg a '
    . 'hozzád legközelebbi borfesztivált, bornapot, kóstolót és szüreti rendezvényt.';
$activeNav = 'terkep';

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'holborozzak.hu');
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$events = [];
try {
    $events = fetchUpcomingEvents(db());
} catch (Throwable $e) {
    error_log('terkep.php DB hiba: ' . $e->getMessage());
}

// Térképpontok (csak koordinátával rendelkező események)
$points = [];
foreach ($events as $e) {
    if (!empty($e['latitude']) && !empty($e['longitude'])) {
        $points[] = [
            'title'  => $e['title'],
            'lat'    => (float) $e['latitude'],
            'lng'    => (float) $e['longitude'],
            'date'   => formatDateRange($e['start_datetime'], $e['end_datetime']),
            'city'   => $e['city'],
            'venue'  => $e['venue_name'],
            'free'   => (int) $e['is_free'] === 1,
            'url'    => eventUrl($e),
            'img'    => eventImage($e),
            'status' => eventStatus($e['start_datetime'], $e['end_datetime']),
            'cats'   => categoryNames($e),
            'feat'   => (int) $e['is_featured'] === 1,
        ];
    }
}

// SEO / AI strukturált adat (közös függvény)
$ld = eventsItemListJsonLd($events, $base, $dir, 'Borrendezvények Magyarországon — térkép');
if ($ld) {
    $jsonLd = $ld;
}

// Leaflet CSS (csak ezen az oldalon)
$headExtra = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"'
    . ' integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">'
    . '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">';

require __DIR__ . '/partials/header.php';
?>
  <div class="map-topbar">
    <h1>Eseménytérkép</h1>
    <p class="map-topbar__sub">Nézd meg térképen, mely borrendezvények vannak a közeledben — <?= count($events) ?> esemény.</p>
  </div>

  <div class="map-split">
    <aside class="map-list" aria-label="Események a térképen">
      <p class="map-list__hint">Kattints egy eseményre — a térkép odaugrik.</p>
      <?php foreach ($points as $i => $p): ?>
        <a class="map-item" href="<?= h($p['url']) ?>" data-i="<?= $i ?>">
          <img class="map-item__img" src="<?= h($p['img']) ?>" alt="" loading="lazy">
          <span class="map-item__main">
            <?php if ($p['feat']): ?><span class="map-item__pill">★ Kiemelt</span><?php endif; ?>
            <span class="map-item__t"><?= h($p['title']) ?></span>
            <span class="map-item__d"><?= h($p['date']) ?></span>
            <span class="map-item__s"><?= h(trim(($p['venue'] ? $p['venue'] . ', ' : '') . ($p['city'] ?? ''))) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
      <?php if (!$points): ?>
        <p class="map-list__hint">Jelenleg nincs térképen megjeleníthető esemény. <a href="esemenyek">Nézd meg a listát →</a></p>
      <?php endif; ?>
    </aside>
    <div id="map" class="event-map" role="region" aria-label="Borrendezvények térképe"></div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script>
  (function () {
    if (typeof L === 'undefined') { return; }
    var pts = <?= json_encode($points, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    var grape = '<span class="grape-pin__icon"><svg viewBox="0 0 24 24" fill="currentColor">'
      + '<path d="M12 6.4c0-2 1.4-3.4 3.6-3.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
      + '<circle cx="10" cy="9" r="1.7"/><circle cx="14" cy="9" r="1.7"/>'
      + '<circle cx="8" cy="12.5" r="1.7"/><circle cx="12" cy="12.5" r="1.7"/><circle cx="16" cy="12.5" r="1.7"/>'
      + '<circle cx="10" cy="16" r="1.7"/><circle cx="14" cy="16" r="1.7"/><circle cx="12" cy="19.2" r="1.5"/></svg></span>';

    // Kiemelt esemény: arany borérem — korong belső gyűrűvonallal + mini szőlőfürt
    var medal = '<svg viewBox="0 0 24 24" aria-hidden="true">'
      + '<circle cx="12" cy="12" r="10.2" class="feat-medal__disc"/>'
      + '<circle cx="12" cy="12" r="7.6" class="feat-medal__ring"/>'
      + '<circle cx="12" cy="9.4" r="1.35"/><circle cx="10.4" cy="11.9" r="1.35"/>'
      + '<circle cx="13.6" cy="11.9" r="1.35"/><circle cx="12" cy="14.4" r="1.35"/>'
      + '</svg>';

    // Fürt: szőlőfürt + darabszám; ha kiemelt is van alatta: mini érem a vállán
    function clusterIcon(count, hasFeat) {
      return L.divIcon({
        className: 'grape-pin',
        html: grape
          + (hasFeat ? '<span class="grape-pin__feat">' + medal + '</span>' : '')
          + '<span class="grape-pin__count">' + count + '</span>',
        iconSize: [46, 46], iconAnchor: [23, 23]
      });
    }
    var dotIcon = L.divIcon({ className: 'grape-dot', html: '', iconSize: [18, 18], iconAnchor: [9, 9], popupAnchor: [0, -10] });
    var featIcon = L.divIcon({ className: 'feat-medal', html: medal, iconSize: [26, 26], iconAnchor: [13, 13], popupAnchor: [0, -14] });

    // closePopupOnClick: false — mobilon a nagy popup auto-pan-je után az érintés
    // „szellem-kattintásként" a térképre érkezne, és azonnal bezárná a kártyát.
    // Helyette saját kezelő zár (lásd lent), ami a megnyitás utáni pillanatban
    // érkező kattintást figyelmen kívül hagyja.
    var map = L.map('map', { scrollWheelZoom: true, closePopupOnClick: false }).setView([47.16, 19.50], 7);

    // Térképre (nem jelölőre) kattintva is záródjon a kártya — de a megnyitást
    // követő „szellem-kattintás" ne zárja be azonnal.
    var popupOpenedAt = 0;
    map.on('popupopen', function () { popupOpenedAt = Date.now(); });
    map.on('click', function () {
      if (Date.now() - popupOpenedAt < 500) { return; }
      map.closePopup();
    });
    // Téma-követő csempék: sötét módban sötét CARTO-alaptérkép
    function tileUrl(theme) {
      return 'https://{s}.basemaps.cartocdn.com/' + (theme === 'dark' ? 'dark_all' : 'light_all') + '/{z}/{x}/{y}{r}.png';
    }
    var tiles = L.tileLayer(tileUrl(document.documentElement.getAttribute('data-theme')), {
      maxZoom: 19, subdomains: 'abcd',
      attribution: '&copy; OpenStreetMap, &copy; CARTO'
    }).addTo(map);
    document.addEventListener('hb-theme-change', function (ev) {
      tiles.setUrl(tileUrl(ev.detail));
    });

    // Zoom-alapú összevonás: kicsinyítve aggregál, ráközelítve szétválik
    var cluster = L.markerClusterGroup({
      showCoverageOnHover: false,
      maxClusterRadius: 50,
      // A nagy kártya auto-pan-je a jelölőt a látótérből kitolhatja; alapból a
      // cluster ilyenkor eltávolítja a jelölőt, ami azonnal bezárja a popupját.
      removeOutsideVisibleBounds: false,
      iconCreateFunction: function (c) {
        var hasFeat = c.getAllChildMarkers().some(function (m) { return m.options.feat; });
        return clusterIcon(c.getChildCount(), hasFeat);
      }
    });

    function popupHtml(p) {
      var h = '<div class="map-card">';
      if (p.img) {
        h += '<a class="map-card__media" href="' + esc(p.url) + '"><img src="' + esc(p.img) + '" alt=""></a>';
      }
      h += '<div class="map-card__body">';
      if (p.status) { h += '<div class="map-card__status"><span class="status ' + esc(p.status.class) + '">' + esc(p.status.label) + '</span></div>'; }
      h += '<a class="map-card__title" href="' + esc(p.url) + '">' + esc(p.title) + '</a>';
      h += '<div class="map-card__date">' + esc(p.date) + '</div>';
      var loc = (p.venue ? p.venue + ', ' : '') + (p.city || '');
      if (loc) { h += '<div class="map-card__loc">📍 ' + esc(loc) + '</div>'; }
      var tags = '';
      (p.cats || []).forEach(function (c) { tags += '<span class="tag">' + esc(c) + '</span>'; });
      if (p.free) { tags += '<span class="tag tag--free">Ingyenes</span>'; }
      if (tags) { h += '<div class="map-card__tags">' + tags + '</div>'; }
      h += '<a class="btn btn--primary map-card__btn" href="' + esc(p.url) + '">Részletek &rarr;</a>';
      h += '</div></div>';
      return h;
    }

    // Mobilon keskenyebb kártya: jobban elfér a kisebb térképen, kevesebb auto-pan kell
    var POPUP_W = window.matchMedia('(max-width: 560px)').matches ? 224 : 260;
    // A kártya soha ne legyen magasabb a térképnél (mobilon 48vh): ha nem fér ki,
    // görgethető lesz, és az auto-pan-nek sem kell a jelölőt a látótérből kitolnia.
    var mapEl = document.getElementById('map');
    var POPUP_MAX_H = Math.max(200, Math.round((mapEl ? mapEl.clientHeight : 400) * 0.72));

    var bounds = [];
    var markers = [];
    pts.forEach(function (p) {
      // Kiemelt: érem-ikon + magasabb z-index, hogy ne bújjon szomszéd pötty alá;
      // a feat flag a marker optionsben marad — a fürt-ikon ebből tudja a jelvényt
      var m = L.marker([p.lat, p.lng], { icon: p.feat ? featIcon : dotIcon, feat: p.feat, zIndexOffset: p.feat ? 1000 : 0 })
        .bindPopup(popupHtml(p), { maxWidth: POPUP_W, minWidth: POPUP_W, maxHeight: POPUP_MAX_H, className: 'event-popup' });
      cluster.addLayer(m);
      markers.push(m);
      bounds.push([p.lat, p.lng]);
    });
    map.addLayer(cluster);
    if (bounds.length) { map.fitBounds(bounds, { padding: [50, 50], maxZoom: 8 }); }

    // Bal oldali lista → térkép: kattintásra odaközelít és kinyitja a kártyát.
    // (JS nélkül a lista-elem az esemény oldalára visz — progresszív fejlesztés.)
    document.querySelectorAll('.map-item').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var m = markers[parseInt(a.getAttribute('data-i'), 10)];
        if (!m) { return; }
        e.preventDefault();
        document.querySelectorAll('.map-item.is-active').forEach(function (x) { x.classList.remove('is-active'); });
        a.classList.add('is-active');
        cluster.zoomToShowLayer(m, function () { m.openPopup(); });
      });
    });

    // „Helyzetem” gomb a zoom alatt: a saját pozícióra közelít
    var LocateControl = L.Control.extend({
      options: { position: 'topleft' },
      onAdd: function (m) {
        var box = L.DomUtil.create('div', 'leaflet-bar locate-control');
        var btn = L.DomUtil.create('a', '', box);
        btn.href = '#';
        btn.title = 'Közelíts a helyzetemre';
        btn.setAttribute('role', 'button');
        btn.setAttribute('aria-label', 'Helyzetem');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'
          + '<circle cx="12" cy="12" r="6"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/>'
          + '<line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/>'
          + '<circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>';
        L.DomEvent.on(btn, 'click', function (e) {
          L.DomEvent.preventDefault(e); L.DomEvent.stopPropagation(e);
          m.locate({ setView: true, maxZoom: 11 });
        });
        return box;
      }
    });
    map.addControl(new LocateControl());

    map.on('locationfound', function (e) {
      if (window._youMarker) { map.removeLayer(window._youMarker); }
      window._youMarker = L.circleMarker(e.latlng, {
        radius: 8, color: '#2a6fb0', fillColor: '#3a8fe0', fillOpacity: .9, weight: 2
      }).addTo(map).bindPopup('Itt vagy');
    });
    map.on('locationerror', function () {
      alert('Nem sikerült meghatározni a helyzetedet. Engedélyezd a helymeghatározást a böngészőben.');
    });
  })();
  </script>
<?php
require __DIR__ . '/partials/footer.php';
