/* holborozzak.hu — progresszív fejlesztés.
 * A tabok és szűrők csak az #esemenyek-region tartalmát töltik újra (nincs
 * felugrás a lap tetejére), az URL frissül. JS nélkül minden sima linkként/
 * űrlapként működik (szerveroldali renderelés), így SEO-barát marad.
 *
 * Szűrők (multiselect): a legördülőben több checkbox is bejelölhető; az
 * alkalmazás a legördülő bezárásakor történik. A rendezés azonnal hat. */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  var REGION = 'esemenyek-region';
  var lastUrl = null;

  function buildQuery(form) {
    var params = new URLSearchParams(new FormData(form));
    var clean = new URLSearchParams();
    params.forEach(function (v, k) {
      if (v === '') { return; }
      if (k === 'rendezes' && v === 'datum') { return; }
      if (k === 'nezet' && v === 'kozelgo') { return; }
      clean.append(k, v);
    });
    // Az alap mindig az űrlap action-je (esemenyek.php) — üres szűrőnél is az
    // események oldalon maradunk, nem a könyvtár gyökerén (ami a főoldal lenne).
    var base = form.getAttribute('action') || 'esemenyek';
    var qs = clean.toString();
    return qs ? (base + '?' + qs) : base;
  }

  function load(url, push) {
    if (url === lastUrl) { return; }   // duplikált kérés kiszűrése
    lastUrl = url;
    var current = document.getElementById(REGION);
    if (!current) { window.location.href = url; return; }
    current.classList.add('is-loading');

    fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.getElementById(REGION);
        if (!next) { window.location.href = url; return; }
        current.replaceWith(document.importNode(next, true));
        if (push) { history.pushState({ url: url }, '', url); }
        if (doc.title) { document.title = doc.title; }
      })
      .catch(function () { window.location.href = url; });
  }

  function closeFacets() {
    var open = document.querySelectorAll('#' + REGION + ' details[data-facet][open]');
    Array.prototype.forEach.call(open, function (d) { d.removeAttribute('open'); });
  }

  // Tab / „Szűrők törlése” linkek; illetve kattintás a szűrőn kívül → zárás
  document.addEventListener('click', function (e) {
    var a = e.target.closest('#' + REGION + ' .tabs a, #' + REGION + ' .facets__clear, #' + REGION + ' .facets__search-clear');
    if (a) { e.preventDefault(); load(a.getAttribute('href'), true); return; }
    if (!e.target.closest('#' + REGION + ' details[data-facet]')) { closeFacets(); }
  });

  // Rendezés (egyválasztós) → azonnali alkalmazás
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('#' + REGION + ' .facets select');
    if (!sel || !sel.form) { return; }
    load(buildQuery(sel.form), true);
  });

  // Szűrő legördülő: nyitáskor pillanatkép, záráskor alkalmazás, ha változott.
  // (A toggle esemény nem buborékol → capture fázisban figyeljük.)
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d.matches || !d.matches('#' + REGION + ' details[data-facet]')) { return; }
    var form = d.closest('form.facets');
    if (!form) { return; }
    if (d.open) {
      d._snapshot = buildQuery(form);
    } else if (d._snapshot !== undefined && buildQuery(form) !== d._snapshot) {
      load(buildQuery(form), true);
    }
  }, true);

  // No-JS gomb / Enter → űrlap-küldés elfogása
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('#' + REGION + ' .facets');
    if (!form) { return; }
    e.preventDefault();
    load(buildQuery(form), true);
  });

  // ----- Naptár: nézetváltó (Lista/Rács) — csak mobilon látszik -----
  var CAL_VIEW_KEY = 'hb_cal_view';
  var cal = document.querySelector('.cal');

  function applyCalView(view) {
    if (!cal) { return; }
    var grid = view === 'grid';
    cal.classList.toggle('is-grid', grid);
    cal.classList.toggle('is-list', !grid);
    var btns = cal.querySelectorAll('.cal-viewtoggle__btn');
    Array.prototype.forEach.call(btns, function (b) {
      var on = b.getAttribute('data-calview') === view;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  if (cal) {
    var savedView = null;
    try { savedView = localStorage.getItem(CAL_VIEW_KEY); } catch (e) { /* privát mód */ }
    if (savedView === 'grid' || savedView === 'list') { applyCalView(savedView); }

    cal.addEventListener('click', function (e) {
      var btn = e.target.closest('.cal-viewtoggle__btn');
      if (!btn) { return; }
      var view = btn.getAttribute('data-calview');
      applyCalView(view);
      try { localStorage.setItem(CAL_VIEW_KEY, view); } catch (err) { /* privát mód */ }
    });
  }

  // ----- Naptár: napra koppintva felugró napi eseménylista (Rács nézet) -----
  var calDays = null, calMonth = '';
  (function () {
    var el = document.getElementById('cal-days');
    if (!el) { return; }
    try { calDays = JSON.parse(el.textContent || '{}'); } catch (e) { calDays = {}; }
    calMonth = el.getAttribute('data-month') || '';
  })();

  var sheet = null;

  function closeSheet() {
    if (sheet) { sheet.hidden = true; document.body.classList.remove('day-sheet-open'); }
  }

  function ensureSheet() {
    if (sheet) { return sheet; }
    sheet = document.createElement('div');
    sheet.className = 'day-sheet';
    sheet.hidden = true;
    sheet.innerHTML =
      '<div class="day-sheet__backdrop"></div>' +
      '<div class="day-sheet__panel" role="dialog" aria-modal="true">' +
        '<div class="day-sheet__head"><span class="day-sheet__title"></span>' +
        '<button type="button" class="day-sheet__close" aria-label="Bezárás">&times;</button></div>' +
        '<div class="day-sheet__list"></div>' +
      '</div>';
    document.body.appendChild(sheet);
    sheet.querySelector('.day-sheet__backdrop').addEventListener('click', closeSheet);
    sheet.querySelector('.day-sheet__close').addEventListener('click', closeSheet);
    return sheet;
  }

  function openDaySheet(day) {
    var items = calDays && calDays[day];
    if (!items || !items.length) { return; }
    ensureSheet();
    sheet.querySelector('.day-sheet__title').textContent = (calMonth + ' ' + day + '.').trim();

    var list = sheet.querySelector('.day-sheet__list');
    list.innerHTML = '';
    items.forEach(function (it) {
      var row = document.createElement('a');
      row.className = 'day-sheet__item';
      row.href = it.u;

      var dot = document.createElement('span');
      dot.className = 'day-sheet__dot';
      dot.style.background = it.c;

      var body = document.createElement('span');
      body.className = 'day-sheet__body';

      var name = document.createElement('span');
      name.className = 'day-sheet__name';
      if (it.ft) { // kiemelt esemény: arany csillag a cím előtt
        var star = document.createElement('span');
        star.className = 'day-sheet__star';
        star.textContent = '★ ';
        name.appendChild(star);
      }
      name.appendChild(document.createTextNode(it.t));
      if (it.f) {
        var free = document.createElement('span');
        free.className = 'day-sheet__free';
        free.textContent = 'Ingyenes';
        name.appendChild(free);
      }
      body.appendChild(name);

      var subText = [it.d, it.l].filter(Boolean).join(' · ');
      if (subText) {
        var sub = document.createElement('span');
        sub.className = 'day-sheet__sub';
        sub.textContent = subText;
        body.appendChild(sub);
      }

      row.appendChild(dot);
      row.appendChild(body);
      list.appendChild(row);
    });

    sheet.hidden = false;
    document.body.classList.add('day-sheet-open');
  }

  document.addEventListener('click', function (e) {
    var hit = e.target.closest('.cal__hit');
    if (!hit) { return; }
    e.preventDefault();
    openDaySheet(hit.getAttribute('data-day'));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSheet(); }
  });

  // Vissza/előre gomb
  window.addEventListener('popstate', function () { lastUrl = null; load(window.location.href, false); });
})();
