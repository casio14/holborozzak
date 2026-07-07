<?php
// Közös admin-navigáció (modern, aktív menüpont-kiemeléssel).
// A hívó opcionálisan beállíthatja a $ADMIN_PAGE-et; egyébként a futó script fájlnevéből jön.
$__cur = $ADMIN_PAGE ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$__items = [
    'index.php'        => 'Események',
    'jeloltek.php'     => 'Jelöltek',
    'feliratkozok.php' => 'Feliratkozók',
    'beerkezo.php'     => 'Beérkező',
    'statisztika.php'  => 'Statisztika',
];
?>
<header class="adminnav">
  <div class="adminnav__inner">
    <a class="adminnav__brand" href="index.php">
      <span class="adminnav__mark">◆</span> holborozzak <span class="adminnav__tag">admin</span>
    </a>
    <nav class="adminnav__menu">
      <?php foreach ($__items as $__f => $__label): ?>
        <a class="adminnav__link<?= $__cur === $__f ? ' is-active' : '' ?>" href="<?= $__f ?>"><?= $__label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="adminnav__side">
      <a class="adminnav__ext" href="../" target="_blank" rel="noopener">Oldal ↗</a>
      <a class="adminnav__logout" href="logout.php">Kilépés</a>
    </div>
  </div>
</header>
