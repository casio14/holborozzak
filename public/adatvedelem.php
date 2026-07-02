<?php
// Adatkezelési tájékoztató — a ténylegesen működő adatkezelésekhez igazítva.
// (Jogi szöveg: jelentős új funkció — pl. süti-alapú mérés — bevezetésekor frissítendő.)
$pageTitle = 'Adatkezelési tájékoztató — holborozzak.hu';
$pageDescription = 'A holborozzak.hu adatkezelési és adatvédelmi tájékoztatója: milyen adatokat, '
    . 'miért és meddig kezelünk (GDPR).';
require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="legal">
      <h1>Adatkezelési tájékoztató</h1>
      <p class="legal-effective">Hatályos: 2026. július 2. — Jelentős változás esetén ezt a
        tájékoztatót frissítjük, a dátum módosításával.</p>

      <div class="legal-summary" aria-label="Röviden">
        <div class="legal-summary__tile">
          <span class="i">🍪</span>
          <b>Nincs süti</b>
          <p>A nyilvános oldal nem használ sütiket.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">🔒</span>
          <b>IP csak hashelve</b>
          <p>Napi kulccsal, visszafejthetetlenül.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">📧</span>
          <b>Hírlevél önkéntes</b>
          <p>Egy kattintással leiratkozhatsz.</p>
        </div>
        <div class="legal-summary__tile">
          <span class="i">🤝</span>
          <b>Nem adjuk tovább</b>
          <p>Adataidat nem értékesítjük, nem osztjuk meg.</p>
        </div>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">1</span>
        <h2>Az adatkezelő</h2>
        <p>Kiss Patrik (magánszemély), e-mail:
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a>.
          További adatok: <a href="impresszum.php">Impresszum</a>.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">2</span>
        <h2>Milyen adatokat kezelünk?</h2>

        <h3>Látogatottsági statisztika</h3>
        <p>Az eseményekhez tartozó kimenő kattintásoknál (jegyvásárlás, hivatalos oldal)
          naplózzuk: a kattintás típusát és időpontját, a hivatkozó oldalt, a böngésző
          technikai azonosítóját (user agent), valamint az IP-cím <strong>naponta változó
          kulccsal képzett, visszafejthetetlen lenyomatát (hash)</strong>. A nyers IP-címet
          nem tároljuk; a lenyomatok napok között nem kapcsolhatók össze, személyed
          azonosítására nem alkalmasak. Ismert keresőrobotokat nem számolunk.</p>

        <h3>Hírlevél</h3>
        <p>Feliratkozáskor kezeljük az e-mail címedet, a feliratkozás időpontját és egy
          leiratkozáshoz használt technikai azonosítót. Ezekre a hírlevél (üdvözlő levél,
          majd kéthetente esemény-összefoglaló) küldéséhez van szükség.
          <a href="leiratkozas.php">Bármikor leiratkozhatsz</a> — ekkor az adataidat
          azonnal töröljük.</p>

        <h3>Esemény beküldése</h3>
        <p>Ha szervezőként eseményt küldesz be, elkérjük a nevedet és e-mail címedet a
          kapcsolattartáshoz és az adatok ellenőrzéséhez. Ezek <strong>nem jelennek meg
          nyilvánosan</strong> a weboldalon.</p>

        <h3>Admin felület</h3>
        <p>Az oldal adminisztrációs felülete belépési munkamenet-sütit használ — ez
          kizárólag az oda belépő adminisztrátorokat érinti, a látogatókat nem.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">3</span>
        <h2>Cél és jogalap</h2>
        <ul>
          <li><strong>Statisztika:</strong> a weboldal működtetése, fejlesztése és a
            szervezők felé készülő látogatottsági kimutatások — jogalap: az adatkezelő
            jogos érdeke (GDPR 6. cikk (1) f)); az adatok személyhez nem köthetők.</li>
          <li><strong>Hírlevél:</strong> tájékoztatás a közelgő borrendezvényekről —
            jogalap: önkéntes hozzájárulásod (GDPR 6. cikk (1) a)), amelyet a
            leiratkozással bármikor visszavonhatsz.</li>
          <li><strong>Esemény-beküldés:</strong> kapcsolattartás a beküldött esemény
            kapcsán — jogalap: hozzájárulás.</li>
        </ul>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">4</span>
        <h2>Sütik (cookie-k)</h2>
        <p>A nyilvános weboldal <strong>nem használ sütiket</strong>, és nem futtat
          harmadik féltől származó hirdetési vagy követő kódot. Ha a jövőben süti-alapú
          mérést vezetnénk be, azt előzetes hozzájárulást kérő süti-sávval és e
          tájékoztató frissítésével tesszük.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">5</span>
        <h2>Meddig tároljuk az adatokat?</h2>
        <ul>
          <li><strong>Hírlevél-adatok:</strong> a leiratkozásig; leiratkozáskor azonnal
            törlődnek.</li>
          <li><strong>Beküldői adatok:</strong> az esemény kezeléséhez szükséges ideig,
            legfeljebb az esemény törléséig.</li>
          <li><strong>Statisztika:</strong> a hashelt, személyhez nem köthető naplókat
            összesített kimutatások készítéséhez őrizzük meg.</li>
        </ul>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">6</span>
        <h2>Adatfeldolgozók, külső szolgáltatások</h2>
        <p><strong>Tárhely és levélküldés:</strong> Rackhost Zrt. (6722 Szeged, Tisza Lajos
          körút 41.) — a weboldal és az adatbázis az ő szerverein fut, a hírlevelek innen
          kerülnek kiküldésre.</p>
        <p><strong>Térkép-szolgáltatók:</strong> a térképes oldalak megnyitásakor a
          böngésződ a térkép-csempéket és a megjelenítő könyvtárat külső szerverekről
          tölti be (OpenStreetMap/CARTO, unpkg CDN) — ezek a lekéréshez technikailag
          látják az IP-címedet, ahogy bármely weboldal betöltésekor történik. Feléjük
          más adatot nem továbbítunk.</p>
        <p>Adataidat harmadik félnek nem adjuk el és nem adjuk át, az Európai Gazdasági
          Térségen kívülre nem továbbítjuk.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">7</span>
        <h2>A te jogaid</h2>
        <p>Kérheted a rád vonatkozó adatokról való tájékoztatást, azok helyesbítését,
          törlését, kezelésük korlátozását, illetve tiltakozhatsz a kezelés ellen.
          Hozzájáruláson alapuló adatkezelésnél (hírlevél) a hozzájárulást bármikor
          visszavonhatod. Kérelmedet az
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a> címen fogadjuk,
          és legkésőbb 30 napon belül válaszolunk.</p>
      </div>

      <div class="legal-card">
        <span class="legal-card__num" aria-hidden="true">8</span>
        <h2>Jogorvoslat</h2>
        <p>Ha úgy érzed, hogy adataid kezelése sérti a jogszabályokat, panaszt tehetsz a
          Nemzeti Adatvédelmi és Információszabadság Hatóságnál (NAIH) — 1055 Budapest,
          Falk Miksa utca 9–11.; <a href="mailto:ugyfelszolgalat@naih.hu">ugyfelszolgalat@naih.hu</a>;
          naih.hu —, vagy bírósághoz fordulhatsz.</p>
      </div>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
