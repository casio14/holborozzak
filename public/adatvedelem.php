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
      <h1 class="legal-formal-title">Adatkezelési tájékoztató</h1>
      <p class="legal-formal-effective">Hatályos: 2026. július 3.</p>
      <p class="legal-formal-note">Jelentős változás esetén ezt a tájékoztatót frissítjük,
        a dátum módosításával.</p>
      <hr class="legal-formal-rule">

      <div class="legal-formal-brief" aria-label="Röviden">
        <b>Röviden:</b>
        <ul>
          <li>🍪 Egy anonim mérési süti — csak ha elfogadod.</li>
          <li>🔒 Az IP-címet csak napi kulccsal, visszafejthetetlenül hashelve tároljuk.</li>
          <li>📧 A hírlevél önkéntes — egy kattintással leiratkozhatsz.</li>
          <li>🤝 Adataidat nem értékesítjük, nem osztjuk meg.</li>
        </ul>
      </div>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">1. §</span>Az adatkezelő</h2>
        <p>A szolgáltató neve: Holborozzak, e-mail:
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a>.
          További adatok: <a href="impresszum.php">Impresszum</a>.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">2. §</span>Milyen adatokat kezelünk?</h2>

        <h3>Látogatottsági statisztika</h3>
        <p>Az esemény-részletoldalak megtekintésénél és a kimenő kattintásoknál
          (jegyvásárlás, hivatalos oldal) naplózzuk: a művelet típusát és időpontját, a
          hivatkozó oldalt, a böngésző technikai azonosítóját (user agent), valamint az
          IP-cím <strong>naponta változó kulccsal képzett, visszafejthetetlen lenyomatát
          (hash)</strong>. A nyers IP-címet nem tároljuk; a lenyomatok napok között nem
          kapcsolhatók össze, személyed azonosítására nem alkalmasak. Ismert
          keresőrobotokat nem számolunk. Ha hozzájárultál a mérési sütihez (lásd a
          Sütik szakaszt), a naplóbejegyzéshez a süti anonim azonosítója is
          hozzákapcsolódik.</p>

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
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">3. §</span>Cél és jogalap</h2>
        <ul>
          <li><strong>Statisztika:</strong> a weboldal működtetése, fejlesztése és a
            szervezők felé készülő látogatottsági kimutatások — jogalap: az adatkezelő
            jogos érdeke (GDPR 6. cikk (1) f)); az adatok személyhez nem köthetők.</li>
          <li><strong>Mérési süti:</strong> a visszatérő látogatók pontosabb, anonim
            mérése — jogalap: önkéntes hozzájárulásod (GDPR 6. cikk (1) a)), amelyet a
            süti-sávon adsz meg, és a sütik törlésével bármikor visszavonhatsz.</li>
          <li><strong>Hírlevél:</strong> tájékoztatás a közelgő borrendezvényekről —
            jogalap: önkéntes hozzájárulásod (GDPR 6. cikk (1) a)), amelyet a
            leiratkozással bármikor visszavonhatsz.</li>
          <li><strong>Esemény-beküldés:</strong> kapcsolattartás a beküldött esemény
            kapcsán — jogalap: hozzájárulás.</li>
        </ul>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">4. §</span>Sütik (cookie-k)</h2>
        <p>A weboldal <strong>kizárólag hozzájárulásod esetén</strong> használ egyetlen,
          saját (first-party) mérési sütit — harmadik féltől származó hirdetési vagy
          követő kódot nem futtatunk.</p>
        <ul>
          <li><strong><code>hb_consent</code></strong> — a süti-sávon hozott döntésedet
            (elfogadás/elutasítás) jegyzi meg 180 napig, hogy ne kérdezzük újra.</li>
          <li><strong><code>hb_sid</code></strong> — csak elfogadás esetén jön létre:
            véletlenszerűen generált, <strong>anonim mérési azonosító</strong> (365 nap),
            amely nem tartalmaz és nem is kapcsolható személyes adathoz. Arra szolgál,
            hogy a látogatottsági statisztikában a visszatérő látogatókat pontosabban,
            duplaszámolás nélkül lássuk.</li>
        </ul>
        <p>Ha a sávon a „Nem fogadom el" lehetőséget választod, mérési süti nem jön
          létre, és az oldal minden funkciója ugyanúgy működik. Döntésedet később a
          böngésző sütijeinek törlésével vonhatod vissza vagy változtathatod meg —
          ekkor a süti-sáv újra megjelenik.</p>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">5. §</span>Meddig tároljuk az adatokat?</h2>
        <ul>
          <li><strong>Hírlevél-adatok:</strong> a leiratkozásig; leiratkozáskor azonnal
            törlődnek.</li>
          <li><strong>Beküldői adatok:</strong> az esemény kezeléséhez szükséges ideig,
            legfeljebb az esemény törléséig.</li>
          <li><strong>Statisztika:</strong> a hashelt, személyhez nem köthető naplókat
            összesített kimutatások készítéséhez őrizzük meg.</li>
        </ul>
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">6. §</span>Adatfeldolgozók, külső szolgáltatások</h2>
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
      </section>

      <section class="legal-formal-sec">
        <h2><span class="legal-formal-par" aria-hidden="true">7. §</span>A te jogaid</h2>
        <p>Kérheted a rád vonatkozó adatokról való tájékoztatást, azok helyesbítését,
          törlését, kezelésük korlátozását, illetve tiltakozhatsz a kezelés ellen.
          Hozzájáruláson alapuló adatkezelésnél (hírlevél) a hozzájárulást bármikor
          visszavonhatod. Kérelmedet az
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a> címen fogadjuk,
          és legkésőbb 30 napon belül válaszolunk. Panasszal a Nemzeti Adatvédelmi és
          Információszabadság Hatósághoz (<a href="https://naih.hu" target="_blank" rel="noopener">naih.hu</a>)
          fordulhatsz.</p>
      </section>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
