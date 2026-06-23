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
          <a href="esemenyek.php">Összes esemény</a>
          <a href="naptar.php">Eseménynaptár</a>
          <a href="terkep.php">Eseménytérkép</a>
          <a href="mailto:info@holborozzak.hu?subject=Hi%C3%A1nyz%C3%B3%20esem%C3%A9ny">Hiányzik egy esemény?</a>
        </nav>

        <nav class="site-footer__col" aria-label="Kategóriák">
          <h3>Kategóriák</h3>
          <a href="esemenyek.php?kategoria%5B%5D=borfesztival">Borfesztiválok</a>
          <a href="esemenyek.php?kategoria%5B%5D=kostolo">Kóstolók</a>
          <a href="esemenyek.php?kategoria%5B%5D=szureti-rendezveny">Szüreti rendezvények</a>
          <a href="esemenyek.php?kategoria%5B%5D=gasztronomia">Gasztronómia</a>
          <a href="esemenyek.php?kategoria%5B%5D=koncert">Koncertek</a>
        </nav>

        <nav class="site-footer__col" aria-label="Borvidékek">
          <h3>Borvidékek</h3>
          <a href="esemenyek.php?borvidek%5B%5D=tokaji">Tokaji</a>
          <a href="esemenyek.php?borvidek%5B%5D=villanyi">Villányi</a>
          <a href="esemenyek.php?borvidek%5B%5D=egri">Egri</a>
          <a href="esemenyek.php?borvidek%5B%5D=badacsonyi">Badacsonyi</a>
          <a href="esemenyek.php?borvidek%5B%5D=soproni">Soproni</a>
        </nav>

        <nav class="site-footer__col site-footer__col--legal" aria-label="Jogi információk">
          <h3>Jogi</h3>
          <a href="impresszum.php">Impresszum</a>
          <a href="aszf.php">ÁSZF</a>
          <a href="adatvedelem.php">Adatvédelem</a>
        </nav>

      </div>

      <div class="site-footer__bottom">
        <span>© <?= date('Y') ?> Holborozzak — Minden jog fenntartva.</span>
        <span class="site-footer__meta">🍷 Készült borszeretőknek · <?= htmlspecialchars('v' . $APP_VERSION, ENT_QUOTES) ?></span>
      </div>
    </div>
  </footer>
</body>
</html>
