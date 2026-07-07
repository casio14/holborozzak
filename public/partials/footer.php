<?php
/**
 * Közös lábléc + dokumentum zárás. A verziót a CI-generált version.php adja.
 */
$APP_VERSION = 'dev';
$versionFile = __DIR__ . '/../version.php';
if (is_file($versionFile)) {
    include $versionFile; // beállítja: $APP_VERSION
}
?>
  </main>
  <footer class="site-footer">
    <div class="site-footer__inner">
      <div class="site-footer__top">

        <div class="site-footer__brand">
          <span class="site-footer__logo">hol<b>borozzak</b>.hu</span>
          <p>Magyarország borrendezvényei egy helyen — borfesztiválok, bornapok, kóstolók
            és szüreti programok. Fedezd fel a hozzád legközelebbi eseményt.</p>
        </div>

        <nav class="site-footer__col" aria-label="Felfedezés">
          <h3>Felfedezés</h3>
          <a href="esemenyek">Összes esemény</a>
          <a href="naptar">Eseménynaptár</a>
          <a href="terkep">Eseménytérkép</a>
          <a href="./#hirlevel">Hírlevél</a>
        </nav>

        <nav class="site-footer__col" aria-label="Szervezőknek">
          <h3>Szervezőknek</h3>
          <a href="esemeny-bekuldes">Esemény beküldése</a>
          <a href="mailto:info@holborozzak.hu?subject=Esem%C3%A9ny%20kiemel%C3%A9se">Kiemelés &amp; hirdetés</a>
          <a href="mailto:info@holborozzak.hu?subject=Hi%C3%A1nyz%C3%B3%20esem%C3%A9ny">Hiányzó esemény jelzése</a>
        </nav>

        <nav class="site-footer__col" aria-label="Kategóriák">
          <h3>Kategóriák</h3>
          <a href="esemenyek?kategoria%5B%5D=borfesztival">Borfesztiválok</a>
          <a href="esemenyek?kategoria%5B%5D=kostolo">Kóstolók</a>
          <a href="esemenyek?kategoria%5B%5D=szureti-rendezveny">Szüreti rendezvények</a>
          <a href="esemenyek?kategoria%5B%5D=gasztronomia">Gasztronómia</a>
          <a href="esemenyek?kategoria%5B%5D=koncert">Koncertek</a>
          <a href="esemenyek#esemenyek-region" class="site-footer__col-all">Összes kategória →</a>
        </nav>

        <nav class="site-footer__col" aria-label="Borvidékek">
          <h3>Borvidékek</h3>
          <a href="esemenyek?borvidek%5B%5D=tokaji">Tokaji</a>
          <a href="esemenyek?borvidek%5B%5D=villanyi">Villányi</a>
          <a href="esemenyek?borvidek%5B%5D=egri">Egri</a>
          <a href="esemenyek?borvidek%5B%5D=badacsonyi">Badacsonyi</a>
          <a href="esemenyek?borvidek%5B%5D=soproni">Soproni</a>
          <a href="esemenyek#esemenyek-region" class="site-footer__col-all">Összes borvidék →</a>
        </nav>

        <nav class="site-footer__col site-footer__col--legal" aria-label="Jogi információk">
          <h3>Jogi</h3>
          <a href="impresszum">Impresszum</a>
          <a href="aszf">ÁSZF</a>
          <a href="adatvedelem">Adatvédelem</a>
        </nav>

      </div>

      <div class="site-footer__bottom">
        <span>© <?= date('Y') ?> Holborozzak — Minden jog fenntartva.</span>
        <span class="site-footer__meta">🍷 Készült borszeretőknek · <?= htmlspecialchars('v' . $APP_VERSION, ENT_QUOTES) ?></span>
      </div>
    </div>
  </footer>

  <?php /* Süti-sáv: csak amíg nincs döntés (hb_consent süti). Elfogadáskor a JS
           anonim mérési azonosítót (hb_sid) állít be — személyes adat nélkül. */ ?>
  <?php if (!isset($_COOKIE['hb_consent'])): ?>
  <div class="cookie-bar" id="cookieBar" role="region" aria-label="Süti tájékoztató">
    <p class="cookie-bar__text">🍪 Egyetlen anonim mérési sütit használnánk, hogy pontosabban
      lássuk a látogatottságot — személyes adatot nem tárol, harmadik fél nem fér hozzá.
      Részletek: <a href="adatvedelem">Adatkezelési tájékoztató</a>.</p>
    <div class="cookie-bar__actions">
      <button type="button" class="btn btn--primary cookie-bar__btn" id="cookieYes">Elfogadom</button>
      <button type="button" class="btn cookie-bar__btn cookie-bar__btn--no" id="cookieNo">Nem fogadom el</button>
    </div>
  </div>
  <?php endif; ?>
  <script>
  (function () {
    function setCookie(n, v, days) {
      var s = n + '=' + v + ';path=/;max-age=' + (days * 86400) + ';SameSite=Lax';
      if (location.protocol === 'https:') { s += ';Secure'; }
      document.cookie = s;
    }
    function getCookie(n) {
      var m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)'));
      return m ? m[1] : null;
    }
    // Anonim mérési azonosító (32 hex) — csak hozzájárulás után létezik
    function ensureSid() {
      if (/^[a-f0-9]{32}$/.test(getCookie('hb_sid') || '')) { return; }
      var b = new Uint8Array(16), h = '', i;
      if (window.crypto && crypto.getRandomValues) { crypto.getRandomValues(b); }
      else { for (i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); } }
      for (i = 0; i < 16; i++) { h += (b[i] + 256).toString(16).slice(1); }
      setCookie('hb_sid', h, 365);
    }
    if (getCookie('hb_consent') === '1') { ensureSid(); }

    // 18+ korellenőrző kapu
    var gate = document.getElementById('ageGate');
    if (gate) {
      var ageYes = document.getElementById('ageYes');
      var ageNo = document.getElementById('ageNo');
      if (ageYes) { ageYes.addEventListener('click', function () {
        setCookie('hb_age', '1', 90);
        document.body.classList.remove('agegate-lock');
        gate.remove();
      }); }
      if (ageNo) { ageNo.addEventListener('click', function () {
        gate.classList.add('is-blocked');
        var card = document.getElementById('ageGateCard');
        if (card) {
          card.innerHTML = '<div class="agegate__mark" aria-hidden="true">🔞</div>'
            + '<h2>Sajnáljuk!</h2>'
            + '<p>Ez az oldal csak 18 éven felülieknek érhető el.</p>'
            + '<div class="agegate__actions"><a class="btn btn--ghost" href="https://www.google.com/">Kilépés</a></div>';
        }
      }); }
    }

    var bar = document.getElementById('cookieBar');
    if (!bar) { return; }
    document.getElementById('cookieYes').addEventListener('click', function () {
      setCookie('hb_consent', '1', 180); ensureSid(); bar.remove();
    });
    document.getElementById('cookieNo').addEventListener('click', function () {
      setCookie('hb_consent', '0', 180); setCookie('hb_sid', '', 0); bar.remove();
    });
  })();
  </script>
</body>
</html>
