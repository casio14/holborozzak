<?php
// ÁSZF — az adatvédelmi oldallal egyező designnal (összefoglaló-csempék + számozott kártyák).
$pageTitle = 'Általános Szerződési Feltételek (ÁSZF) — holborozzak.hu';
$pageDescription = 'A holborozzak.hu használatának általános szerződési feltételei: '
    . 'a szolgáltatás leírása, felelősség, szellemi tulajdon.';
require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="legal">
      <h1>Általános Szerződési Feltételek</h1>
      <p class="legal-effective">Hatályos: 2026. július 2. — A mindenkor hatályos változat
        ezen az oldalon érhető el.</p>

      <div class="legal-summary" aria-label="Röviden">
        <div class="legal-summary__tile">
          <span class="i">🍷</span>
          <b>Ingyenes</b>
          <p>A weboldal használata díjmentes.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">ℹ️</span>
          <b>Tájékoztató jelleg</b>
          <p>Az adatok a szervezőktől és nyilvános forrásokból származnak.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">✅</span>
          <b>Ellenőrizd indulás előtt</b>
          <p>Az időpontok, árak változhatnak.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">⚖️</span>
          <b>Magyar jog</b>
          <p>A nem szabályozott kérdésekben a magyar jog az irányadó.</p>
        </div>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">1</span>
        <h2>A szolgáltató</h2>
        <p>A holborozzak.hu weboldalt a Holborozzak üzemelteti (a továbbiakban:
          Szolgáltató). Elérhetőség:
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a>.
          További adatok: <a href="impresszum.php">Impresszum</a>.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">2</span>
        <h2>A szolgáltatás leírása</h2>
        <p>A weboldal Magyarország borhoz köthető eseményeit (borfesztiválok, bornapok,
          kóstolók, szüreti rendezvények) gyűjti és listázza tájékoztató jelleggel,
          bárki számára ingyenesen elérhető formában. A Szolgáltató az eseményeknek nem
          szervezője és nem jegyértékesítője; a jegyvásárlás minden esetben a szervezők
          vagy jegyértékesítő partnereik oldalán történik.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">3</span>
        <h2>A használat feltételei</h2>
        <p>A weboldal használatával a Felhasználó elfogadja a jelen ÁSZF-et. A tartalmak
          személyes, tájékozódási célra szabadon használhatók. Az esemény-beküldő űrlapon
          a Felhasználó csak valós, általa jogszerűen megosztható információt adhat meg;
          a beküldött események közzétételéről a Szolgáltató dönt.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">4</span>
        <h2>Felelősség</h2>
        <p>Az események adatai (időpont, helyszín, ár, program) a szervezőktől, illetve
          nyilvános forrásokból származnak, és a szervezők döntése alapján bármikor
          változhatnak. A Szolgáltató az adatok pontosságáért, teljességéért és a
          változásokból eredő károkért felelősséget nem vállal — kérjük, indulás előtt
          mindig ellenőrizd a részleteket a rendezvény hivatalos oldalán. A weboldalról
          elérhető külső oldalak tartalmáért a Szolgáltató nem felel.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">5</span>
        <h2>Szellemi tulajdon</h2>
        <p>A weboldal megjelenése, szerkezete és saját készítésű tartalma szerzői jogi
          védelem alatt áll; a Szolgáltató engedélye nélkül üzleti célra nem használható
          fel. Az egyes eseményekhez tartozó nevek, leírások és képek jogai a rendezvények
          szervezőit illetik.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">6</span>
        <h2>Az ÁSZF módosítása</h2>
        <p>A Szolgáltató fenntartja a jogot a jelen ÁSZF egyoldalú módosítására. A
          mindenkor hatályos változat ezen az oldalon érhető el; a módosítás a
          közzététellel lép hatályba.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">7</span>
        <h2>Záró rendelkezések</h2>
        <p>A jelen ÁSZF-ben nem szabályozott kérdésekben a magyar jog — különösen a
          Polgári Törvénykönyv és az elektronikus kereskedelmi szolgáltatásokról szóló
          2001. évi CVIII. törvény — az irányadó. Kapcsolódó dokumentumok:
          <a href="adatvedelem.php">Adatkezelési tájékoztató</a> ·
          <a href="impresszum.php">Impresszum</a></p>
      </div>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
