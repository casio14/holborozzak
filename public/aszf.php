<?php
// ÁSZF — az adatvédelmi oldallal egyező designnal (összefoglaló-csempék + számozott kártyák).
$pageTitle = 'Általános Szerződési Feltételek (ÁSZF) — holborozzak.hu';
$pageDescription = 'A holborozzak.hu használatának általános szerződési feltételei: '
    . 'a szolgáltatás leírása, felelősség, szellemi tulajdon.';
require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="legal">
      <h1 class="legal-formal-title">Általános Szerződési Feltételek</h1>
      <p class="legal-formal-effective">Hatályos: 2026. július 2.</p>
      <hr class="legal-formal-rule">

      <div class="legal-formal-brief" aria-label="Röviden">
        <b>Röviden:</b>
        <ul>
          <li>🍷 A weboldal használata díjmentes.</li>
          <li>ℹ️ Az adatok a szervezőktől és nyilvános forrásokból származnak — tájékoztató jellegűek.</li>
          <li>✅ Indulás előtt ellenőrizd az időpontokat és árakat a hivatalos oldalon.</li>
          <li>⚖️ A nem szabályozott kérdésekben a magyar jog az irányadó.</li>
        </ul>
      </div>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">1. §</span>A szolgáltató</h2>
        <p>A holborozzak.hu weboldalt a Holborozzak üzemelteti (a továbbiakban:
          Szolgáltató). Elérhetőség:
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a>.
          További adatok: <a href="impresszum.php">Impresszum</a>.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">2. §</span>A szolgáltatás leírása</h2>
        <p>A weboldal Magyarország borhoz köthető eseményeit (borfesztiválok, bornapok,
          kóstolók, szüreti rendezvények) gyűjti és listázza tájékoztató jelleggel,
          bárki számára ingyenesen elérhető formában. A Szolgáltató az eseményeknek nem
          szervezője és nem jegyértékesítője; a jegyvásárlás minden esetben a szervezők
          vagy jegyértékesítő partnereik oldalán történik.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">3. §</span>A használat feltételei</h2>
        <p>A weboldal használatával a Felhasználó elfogadja a jelen ÁSZF-et. A tartalmak
          személyes, tájékozódási célra szabadon használhatók. Az esemény-beküldő űrlapon
          a Felhasználó csak valós, általa jogszerűen megosztható információt adhat meg;
          a beküldött események közzétételéről a Szolgáltató dönt.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">4. §</span>Felelősség</h2>
        <p>Az események adatai (időpont, helyszín, ár, program) a szervezőktől, illetve
          nyilvános forrásokból származnak, és a szervezők döntése alapján bármikor
          változhatnak. A Szolgáltató az adatok pontosságáért, teljességéért és a
          változásokból eredő károkért felelősséget nem vállal — kérjük, indulás előtt
          mindig ellenőrizd a részleteket a rendezvény hivatalos oldalán. A weboldalról
          elérhető külső oldalak tartalmáért a Szolgáltató nem felel.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">5. §</span>Szellemi tulajdon</h2>
        <p>A weboldal megjelenése, szerkezete és saját készítésű tartalma szerzői jogi
          védelem alatt áll; a Szolgáltató engedélye nélkül üzleti célra nem használható
          fel. Az egyes eseményekhez tartozó nevek, leírások és képek jogai a rendezvények
          szervezőit illetik.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">6. §</span>Az ÁSZF módosítása</h2>
        <p>A Szolgáltató fenntartja a jogot a jelen ÁSZF egyoldalú módosítására. A
          mindenkor hatályos változat ezen az oldalon érhető el; a módosítás a
          közzététellel lép hatályba.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">7. §</span>Kapcsolat</h2>
        <p>Ha kérdésed van a jelen ÁSZF-fel kapcsolatban, fordulj hozzánk bizalommal az
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a> címen — igyekszünk
          mielőbb válaszolni. Kapcsolódó dokumentumok:
          <a href="adatvedelem.php">Adatkezelési tájékoztató</a> ·
          <a href="impresszum.php">Impresszum</a></p>
      </section>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
