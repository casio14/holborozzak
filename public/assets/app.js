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
    var qs = clean.toString();
    return qs ? ('?' + qs) : './';
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
    var a = e.target.closest('#' + REGION + ' .tabs a, #' + REGION + ' .facets__clear');
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

  // Vissza/előre gomb
  window.addEventListener('popstate', function () { lastUrl = null; load(window.location.href, false); });
})();
