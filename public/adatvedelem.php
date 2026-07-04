<?php
// Adatkezelési tájékoztató — a ténylegesen működő adatkezelésekhez igazítva.
// (Jogi szöveg: jelentős új funkció — pl. süti-alapú mérés — bevezetésekor frissítendő.)
// Egyszerű, hétköznapi nyelven íródott (nem jogászi zsargonnal), de a GDPR-hoz
// szükséges tartalmi elemek (adatkör, cél, jogalap, megőrzés, jogok) megvannak.
$pageTitle = 'Adatkezelési tájékoztató — holborozzak.hu';
$pageDescription = 'A holborozzak.hu adatkezelési és adatvédelmi tájékoztatója: milyen adatokat, '
    . 'miért és meddig kezelünk (GDPR).';
require __DIR__ . '/partials/header.php';
?>
  <div class="container">
    <article class="legal">
      <h1>Adatkezelési tájékoztató</h1>
      <p class="legal-effective">Hatályos: 2026. július 3. — Jelentős változás esetén ezt a
        tájékoztatót frissítjük, a dátum módosításával.</p>
      <hr class="legal-rule">

      <section class="legal-sec">
        <h2>Röviden</h2>
        <ul>
          <li>🍪 Egy anonim mérési süti — csak ha elfogadod.</li>
          <li>🔒 Az IP-címet csak napi kulccsal, visszafejthetetlenül hashelve tároljuk.</li>
          <li>📧 A hírlevél önkéntes — egy kattintással leiratkozhatsz.</li>
          <li>🤝 Adataidat nem értékesítjük, nem osztjuk meg.</li>
        </ul>
      </section>

      <section class="legal-sec">
        <h2>Ki kezeli az adataidat?</h2>
        <address>
          <dl class="legal-kv">
            <dt>Szolgáltató neve</dt><dd>Holborozzak</dd>
            <dt>E-mail</dt><dd><a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a></dd>
            <dt>Bővebben</dt><dd><a href="impresszum.php">Impresszum</a></dd>
          </dl>
        </address>
      </section>

      <section class="legal-sec">
        <h2>Milyen adatokat kezelünk?</h2>

        <h3>Látogatottsági statisztika</h3>
        <p>Amikor megnyitod egy esemény oldalát, vagy rákattintasz egy jegyvásárlási vagy
          honlap-linkre, ezt naplózzuk: mit csináltál, mikor, honnan érkeztél az oldalra, és
          milyen böngészőt használtál. Az IP-címedet nem tároljuk közvetlenül — helyette egy
          olyan kódolt változatát mentjük el, amiből nem lehet visszafejteni, ki voltál, és
          amely naponta megváltozik, így a napok között sem köthető össze. Ismert
          keresőrobotokat (pl. a Google indexelőjét) nem számoljuk bele. Ha elfogadtad a
          mérési sütit (lásd lentebb), a látogatásodhoz egy anonim azonosítót is hozzárendelünk,
          hogy pontosabban lássuk, ki tér vissza.</p>

        <h3>Hírlevél</h3>
        <p>Ha feliratkozol a hírlevélre, eltároljuk az e-mail címedet, a feliratkozás
          időpontját, és egy technikai kódot, amivel bármikor le tudsz iratkozni. Ezekre azért
          van szükség, hogy elküldhessük az üdvözlő levelet, majd kéthetente egy összefoglalót
          a közelgő eseményekről. <a href="leiratkozas.php">Bármikor leiratkozhatsz</a> —
          ilyenkor az adataidat azonnal töröljük.</p>

        <h3>Esemény beküldése</h3>
        <p>Ha szervezőként eseményt küldesz be, elkérjük a neved és e-mail címed, hogy fel
          tudjuk venni veled a kapcsolatot, és ellenőrizni tudjuk az adatokat. Ezeket
          <strong>sosem tesszük közzé</strong> a weboldalon.</p>

        <h3>Admin felület</h3>
        <p>A weboldal adminisztrációs felülete egy belépési sütit használ — ez csak azokat
          érinti, akik be tudnak lépni oda, a látogatókat nem.</p>
      </section>

      <section class="legal-sec">
        <h2>Miért kezeljük az adataidat?</h2>
        <ul>
          <li><strong>Statisztika:</strong> hogy lássuk, hogyan használják az oldalt, javítani
            tudjunk rajta, és kimutatást tudjunk adni a szervezőknek — ezt jogos érdekünkként
            tesszük, az adatok senkihez nem köthetők.</li>
          <li><strong>Mérési süti:</strong> csak akkor használjuk, ha ehhez a süti-sávon
            hozzájárulsz; ezt a sütik törlésével bármikor visszavonhatod.</li>
          <li><strong>Hírlevél:</strong> csak akkor küldjük, ha erre feliratkozol; ezt a
            leiratkozással bármikor visszavonhatod.</li>
          <li><strong>Esemény-beküldés:</strong> azért kezeljük az adataidat, hogy fel tudjunk
            venni veled a kapcsolatot a beküldött eseménnyel kapcsolatban.</li>
        </ul>
      </section>

      <section class="legal-sec">
        <h2>Sütik (cookie-k)</h2>
        <p>A weboldalon <strong>kizárólag hozzájárulásod esetén</strong> helyezünk el egy
          saját mérési sütit — reklámozó vagy követő sütit nem használunk.</p>
        <ul>
          <li><strong><code>hb_consent</code></strong> — megjegyzi, mit válaszoltál a
            süti-kérdésre (elfogadtad vagy nem), így 180 napig nem kérdezzük meg újra.</li>
          <li><strong><code>hb_sid</code></strong> — csak elfogadás esetén jön létre: egy
            véletlenszerű kód (365 napig érvényes), amely nem árulja el, ki vagy — csak
            annyit segít, hogy lássuk, ha valaki visszatér az oldalra.</li>
        </ul>
        <p>Ha a „Nem fogadom el" lehetőséget választod, nem jön létre mérési süti, és az
          oldal minden funkciója ugyanúgy működik. Döntésedet később a böngésződ sütijeinek
          törlésével bármikor megváltoztathatod — ekkor a kérdés újra megjelenik.</p>
      </section>

      <section class="legal-sec">
        <h2>Meddig őrizzük meg az adataidat?</h2>
        <ul>
          <li><strong>Hírlevél-adatok:</strong> a leiratkozásig; leiratkozáskor azonnal
            törlődnek.</li>
          <li><strong>Beküldői adatok:</strong> az esemény kezeléséhez szükséges ideig,
            legfeljebb az esemény törléséig.</li>
          <li><strong>Statisztika:</strong> hosszabb ideig megőrizzük, mert ezekből csak
            összesített, senkihez nem köthető kimutatásokat készítünk.</li>
        </ul>
      </section>

      <section class="legal-sec">
        <h2>Kik látják még az adataidat?</h2>
        <p><strong>Tárhely és levélküldés:</strong> a weboldalt és az adatbázist a Rackhost
          Zrt. (6722 Szeged, Tisza Lajos körút 41.) szerverein üzemeltetjük, innen küldjük
          a hírleveleket is.</p>
        <p><strong>Térkép:</strong> ha megnyitod a térképes oldalakat, a böngésződ betölt egy
          térképet egy külső szolgáltatótól (OpenStreetMap/CARTO) — ők ilyenkor technikailag
          látják az IP-címedet, ugyanúgy, mint bármelyik weboldal betöltésekor.</p>
        <p>Az adataidat nem adjuk el, nem osztjuk meg senkivel, és nem visszük ki az Európai
          Gazdasági Térségen kívülre.</p>
      </section>

      <section class="legal-sec">
        <h2>Milyen jogaid vannak?</h2>
        <p>Bármikor megkérdezheted, milyen adatot tárolunk rólad, kérheted azok javítását
          vagy törlését, korlátozhatod a kezelésüket, illetve tiltakozhatsz a kezelés ellen.
          Ha korábban hozzájárultál valamihez (pl. a hírlevélhez), ezt a hozzájárulást
          bármikor visszavonhatod. Írj nekünk az
          <a href="mailto:info@holborozzak.hu">info@holborozzak.hu</a> címre — legkésőbb
          30 napon belül válaszolunk. Ha úgy érzed, nem kaptál megfelelő választ, panasszal
          fordulhatsz a Nemzeti Adatvédelmi és Információszabadság Hatósághoz
          (<a href="https://naih.hu" target="_blank" rel="noopener">naih.hu</a>).</p>
      </section>
    </article>
  </div>
<?php
require __DIR__ . '/partials/footer.php';
