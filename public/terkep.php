<?php
declare(strict_types=1);

// holborozzak.hu — Eseménytérkép: a borrendezvények interaktív térképen.

require __DIR__ . '/db.php';
require __DIR__ . '/lib/events.php';

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
            'img'    => $e['image_url'] ?: '',
            'status' => eventStatus($e['start_datetime'], $e['end_datetime']),
            'cats'   => categoryNames($e),
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
  <div class="container">
    <div class="map-head">
      <h1>Eseménytérkép</h1>
      <span class="map-head__count"><?= count($events) ?> esemény</span>
    </div>
  </div>

  <div id="map" class="event-map" role="region" aria-label="Borrendezvények térképe"></div>

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

    // Fürt: szőlőfürt + darabszám; egyetlen esemény: egyszerű pont
    function clusterIcon(count) {
      return L.divIcon({
        className: 'grape-pin',
        html: grape + '<span class="grape-pin__count">' + count + '</span>',
        iconSize: [46, 46], iconAnchor: [23, 23]
      });
    }
    var dotIcon = L.divIcon({ className: 'grape-dot', html: '', iconSize: [18, 18], iconAnchor: [9, 9], popupAnchor: [0, -10] });

    var map = L.map('map', { scrollWheelZoom: true }).setView([47.16, 19.50], 7);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      maxZoom: 19, subdomains: 'abcd',
      attribution: '&copy; OpenStreetMap, &copy; CARTO'
    }).addTo(map);

    // Zoom-alapú összevonás: kicsinyítve aggregál, ráközelítve szétválik
    var cluster = L.markerClusterGroup({
      showCoverageOnHover: false,
      maxClusterRadius: 50,
      iconCreateFunction: function (c) { return clusterIcon(c.getChildCount()); }
    });

    function popupHtml(p) {
      var h = '<div class="map-card">';
      if (p.img) {
        h += '<a class="map-card__media" href="' + esc(p.url) + '"><img src="' + esc(p.img) + '" alt=""></a>';
      }
      h += '<div class="map-card__body">';
      h += '<a class="map-card__title" href="' + esc(p.url) + '">' + esc(p.title) + '</a>';
      h += '<div class="map-card__date">' + esc(p.date);
      if (p.status) { h += ' <span class="status ' + esc(p.status.class) + '">' + esc(p.status.label) + '</span>'; }
      h += '</div>';
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

    var bounds = [];
    pts.forEach(function (p) {
      cluster.addLayer(
        L.marker([p.lat, p.lng], { icon: dotIcon })
          .bindPopup(popupHtml(p), { maxWidth: 260, minWidth: 260, className: 'event-popup' })
      );
      bounds.push([p.lat, p.lng]);
    });
    map.addLayer(cluster);
    if (bounds.length) { map.fitBounds(bounds, { padding: [50, 50], maxZoom: 8 }); }

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
