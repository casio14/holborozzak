<?php
declare(strict_types=1);

/**
 * Statikus borvidék-metaadatok a borvidék-oldalakhoz (SEO-tartalom), slug szerint.
 * Szándékosan NEM DB-ben: nincs séma-migráció, és itt könnyen szerkeszthető/bővíthető.
 * Kulcsok: intro (1–2 mondat), grapes (fő szőlőfajták), wines (jellemző borstílus).
 * Ha egy borvidék nincs itt, a borvidék-oldal a nevet + eseményeket akkor is megjeleníti.
 */

return [
    'tokaji' => [
        'intro'  => 'A világ egyik leghíresebb történelmi borvidéke, az UNESCO világörökség része — a legendás Tokaji Aszú és a száraz Furmint hazája.',
        'grapes' => 'Furmint, Hárslevelű, Sárgamuskotály',
        'wines'  => 'Édes és száraz fehér',
    ],
    'egri' => [
        'intro'  => 'Észak-Magyarország ikonikus borvidéke, a testes Egri Bikavér és a friss Egri Csillag otthona, a Szépasszony-völgy híres pincesorával.',
        'grapes' => 'Kékfrankos, Kadarka, Olaszrizling',
        'wines'  => 'Vörös cuvée és fehér',
    ],
    'villanyi' => [
        'intro'  => 'Magyarország egyik legmelegebb borvidéke, mediterrán hangulattal — testes, érlelt vörösborairól, kiváltképp a Cabernet Francról híres.',
        'grapes' => 'Cabernet Franc, Kékfrankos, Portugieser',
        'wines'  => 'Testes vörös',
    ],
    'badacsonyi' => [
        'intro'  => 'A Balaton fölé magasodó vulkanikus tanúhegyek borvidéke, feszes, ásványos fehérborokkal és a ritka Kéknyelűvel.',
        'grapes' => 'Olaszrizling, Kéknyelű, Szürkebarát',
        'wines'  => 'Ásványos fehér',
    ],
    'szekszardi' => [
        'intro'  => 'Löszdombok borvidéke a Dél-Dunántúlon — a fűszeres Szekszárdi Bikavér és a hagyományos Kadarka szülőhelye.',
        'grapes' => 'Kékfrankos, Kadarka, Merlot',
        'wines'  => 'Fűszeres vörös',
    ],
    'soproni' => [
        'intro'  => 'A Fertő tó melletti, kékfrankos-központú borvidék az osztrák határ mentén, hűvösebb, elegáns vörösborokkal.',
        'grapes' => 'Kékfrankos, Zöld Veltelini',
        'wines'  => 'Vörös és fehér',
    ],
    'balatonfured-csopaki' => [
        'intro'  => 'A Balaton északi partjának elegáns borvidéke, világhírű csopaki dűlőkkel és jellegadó Olaszrizlinggel.',
        'grapes' => 'Olaszrizling, Rizlingszilváni',
        'wines'  => 'Ásványos fehér',
    ],
    'balatonboglari' => [
        'intro'  => 'A Balaton déli partjának napfényes borvidéke — üde fehérborokról, rozékról és pezsgőkről egyaránt ismert.',
        'grapes' => 'Királyleányka, Chardonnay, Kékfrankos',
        'wines'  => 'Friss fehér és rozé',
    ],
    'balaton-felvideki' => [
        'intro'  => 'A Balaton-felvidék vulkanikus, tanúhegyes tája — friss, ásványos fehérborok, festői dűlőkkel.',
        'grapes' => 'Olaszrizling, Szürkebarát, Rizlingszilváni',
        'wines'  => 'Ásványos fehér',
    ],
    'etyek-budai' => [
        'intro'  => 'Budapest szomszédságában fekvő, üde fehérborok és kiváló pezsgőalapborok borvidéke.',
        'grapes' => 'Chardonnay, Sauvignon Blanc, Pinot Noir',
        'wines'  => 'Friss fehér és pezsgő',
    ],
    'matrai' => [
        'intro'  => 'A Mátra déli lejtőin fekvő, nagy kiterjedésű borvidék — könnyed, illatos fehérborokkal.',
        'grapes' => 'Olaszrizling, Rizlingszilváni, Muskotály',
        'wines'  => 'Könnyed fehér',
    ],
    'pannonhalmi' => [
        'intro'  => 'A bencés főapátsághoz kötődő, kicsi de kiváló borvidék a Dunántúlon, elegáns, precíz fehérborokkal.',
        'grapes' => 'Olaszrizling, Rajnai Rizling, Tramini',
        'wines'  => 'Elegáns fehér',
    ],
    'nagy-somloi' => [
        'intro'  => 'Egyetlen bazalt tanúhegy köré épült apró borvidék — tüzes, ásványos borok, a jellegzetes Juhfarkkal.',
        'grapes' => 'Juhfark, Hárslevelű, Furmint',
        'wines'  => 'Tüzes, ásványos fehér',
    ],
    'kunsagi' => [
        'intro'  => 'Az ország legnagyobb kiterjedésű borvidéke az Alföld homoktalajain — könnyed, iható borok széles kínálatával.',
        'grapes' => 'Kadarka, Cserszegi Fűszeres, Ezerjó',
        'wines'  => 'Könnyed fehér és vörös',
    ],
    'csongradi' => [
        'intro'  => 'Dél-alföldi, napfényes borvidék a Tisza mentén, gyümölcsös vörös- és rozéborokkal.',
        'grapes' => 'Kadarka, Kékfrankos',
        'wines'  => 'Vörös és rozé',
    ],
    'hajos-bajai' => [
        'intro'  => 'Az Alföld déli részének borvidéke, Hajós híres pincefalujával és testesebb vörösboraival.',
        'grapes' => 'Cabernet Sauvignon, Kékfrankos',
        'wines'  => 'Vörös',
    ],
    'tolnai' => [
        'intro'  => 'A Dél-Dunántúl dombjain fekvő, sokszínű borvidék — vörös- és fehérborok egyaránt.',
        'grapes' => 'Kékfrankos, Chardonnay',
        'wines'  => 'Vörös és fehér',
    ],
    'pecsi' => [
        'intro'  => 'A Mecsek déli lejtőin fekvő, mediterrán hatású borvidék, a helyi Cirfandli különlegességével.',
        'grapes' => 'Olaszrizling, Cirfandli',
        'wines'  => 'Fehér',
    ],
    'mori' => [
        'intro'  => 'A Vértes lábánál fekvő borvidék, a jellegzetesen élénk savú Ezerjó hazája.',
        'grapes' => 'Ezerjó, Tramini',
        'wines'  => 'Élénk savú fehér',
    ],
    'aszar-neszmelyi' => [
        'intro'  => 'A Duna menti dombok borvidéke — friss, illatos, reduktív fehérborokkal.',
        'grapes' => 'Olaszrizling, Chardonnay, Sauvignon Blanc',
        'wines'  => 'Friss fehér',
    ],
    'bukki' => [
        'intro'  => 'Észak-Magyarország kis borvidéke a Bükk lábánál, könnyed, üde fehérborokkal.',
        'grapes' => 'Olaszrizling, Leányka',
        'wines'  => 'Könnyed fehér',
    ],
    'zalai' => [
        'intro'  => 'Nyugat-Magyarország dimbes-dombos borvidéke — üde, gyümölcsös fehérborokkal.',
        'grapes' => 'Olaszrizling, Királyleányka',
        'wines'  => 'Üde fehér',
    ],
];
