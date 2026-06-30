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
    var base = form.getAttribute('action') || 'esemenyek.php';
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

  // ----- Naptár: napra koppintva felugró napi eseménylista -----
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

  function openDaySheet(cell, events) {
    ensureSheet();
    var dayEl = cell.querySelector('.cal__day');
    var monthEl = document.querySelector('.cal-toolbar__month');
    var day = dayEl ? dayEl.textContent.trim() : '';
    var month = monthEl ? monthEl.textContent.trim() : '';
    sheet.querySelector('.day-sheet__title').textContent = (month + ' ' + day + '.').trim();

    var list = sheet.querySelector('.day-sheet__list');
    list.innerHTML = '';
    Array.prototype.forEach.call(events, function (a) {
      var row = document.createElement('a');
      row.className = 'day-sheet__item';
      row.href = a.getAttribute('href');
      var dot = document.createElement('span');
      dot.className = 'day-sheet__dot';
      dot.style.background = a.style.backgroundColor || a.style.background;
      var txt = document.createElement('span');
      txt.textContent = a.getAttribute('title') || a.textContent.trim();
      row.appendChild(dot);
      row.appendChild(txt);
      list.appendChild(row);
    });

    sheet.hidden = false;
    document.body.classList.add('day-sheet-open');
  }

  document.addEventListener('click', function (e) {
    var cell = e.target.closest('.cal__grid .cal__cell');
    if (!cell) { return; }
    var events = cell.querySelectorAll('.cal__event');
    if (!events.length) { return; }
    var isMobile = window.matchMedia('(max-width: 560px)').matches;
    var moreClick = e.target.closest('.cal__more');
    if (isMobile || moreClick) {
      e.preventDefault();
      openDaySheet(cell, events);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSheet(); }
  });

  // Vissza/előre gomb
  window.addEventListener('popstate', function () { lastUrl = null; load(window.location.href, false); });
})();
