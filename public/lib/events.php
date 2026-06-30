<?php
declare(strict_types=1);

/**
 * Esemény-lekérdezések és megjelenítési segédfüggvények.
 * Igényli: db.php (db() függvény).
 */

const HU_MONTHS = [
    1 => 'Január', 2 => 'Február', 3 => 'Március', 4 => 'Április',
    5 => 'Május', 6 => 'Június', 7 => 'Július', 8 => 'Augusztus',
    9 => 'Szeptember', 10 => 'Október', 11 => 'November', 12 => 'December',
];
const HU_MONTHS_SHORT = [
    1 => 'jan.', 2 => 'feb.', 3 => 'márc.', 4 => 'ápr.', 5 => 'máj.', 6 => 'jún.',
    7 => 'júl.', 8 => 'aug.', 9 => 'szept.', 10 => 'okt.', 11 => 'nov.', 12 => 'dec.',
];

/** Engedélyezett nézetek (tabok): kulcs => felirat. */
const EVENT_VIEWS = [
    'kozelgo'  => 'Közelgő',
    'het-vege' => 'E hétvégén',
    'ho'       => 'E hónapban',
    'ingyenes' => 'Ingyenes',
];

/** A nézet-kulcs ellenőrzése (ismeretlen → alapértelmezett). */
function normalizeView(?string $v): string
{
    return isset(EVENT_VIEWS[$v]) ? $v : 'kozelgo';
}

/** Rendezési lehetőségek: kulcs => felirat. */
const EVENT_SORTS = [
    'datum'      => 'Legközelebbi',
    'datum-desc' => 'Legkésőbbi',
    'nev'        => 'Név szerint',
];

/** A rendezés-kulcs ellenőrzése (ismeretlen → alapértelmezett). */
function normalizeSort(?string $s): string
{
    return isset(EVENT_SORTS[$s]) ? $s : 'datum';
}

/** A közelgő hétvége (szombat 00:00 – vasárnap 23:59). */
function weekendRange(DateTimeImmutable $now): array
{
    $dow = (int) $now->format('N');           // 1=hétfő .. 7=vasárnap
    $daysToSat = (6 - $dow + 7) % 7;          // hány nap a szombatig
    $satStart = $now->modify("+{$daysToSat} days")->setTime(0, 0, 0);
    $sunEnd   = $satStart->modify('+1 day')->setTime(23, 59, 59);
    return [$satStart, $sunEnd];
}

/** Az aktuális hónap eleje–vége. */
function monthRange(DateTimeImmutable $now): array
{
    return [
        $now->modify('first day of this month')->setTime(0, 0, 0),
        $now->modify('last day of this month')->setTime(23, 59, 59),
    ];
}

/** Átfedés-vizsgálat: az esemény beleér-e a [rs, re] időszakba. */
function eventOverlaps(array $e, DateTimeImmutable $rs, DateTimeImmutable $re): bool
{
    $s = new DateTimeImmutable($e['start_datetime']);
    $end = !empty($e['end_datetime']) ? new DateTimeImmutable($e['end_datetime']) : $s;
    return $s <= $re && $end >= $rs;
}

/** A nézet szerinti szűrés (a már betöltött közelgő eseményeken). */
function filterEvents(array $events, string $view): array
{
    if ($view === 'ingyenes') {
        return array_values(array_filter($events, static fn($e) => (int) $e['is_free'] === 1));
    }
    if ($view === 'het-vege' || $view === 'ho') {
        $now = new DateTimeImmutable('now');
        [$rs, $re] = $view === 'het-vege' ? weekendRange($now) : monthRange($now);
        return array_values(array_filter($events, static fn($e) => eventOverlaps($e, $rs, $re)));
    }
    return $events; // kozelgo
}

/** Az összes közelgő/most zajló, közzétett esemény, címkékkel és borvidékkel. */
function fetchUpcomingEvents(PDO $pdo): array
{
    $sql = "SELECT e.*, r.name AS region_name, r.slug AS region_slug, r.image_url AS region_image_url,
                   GROUP_CONCAT(DISTINCT CONCAT(c.slug, '\\t', c.name) ORDER BY c.name SEPARATOR '||') AS cat_pairs
            FROM events e
            LEFT JOIN wine_regions r ON r.id = e.region_id
            LEFT JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN categories c ON c.id = ec.category_id
            WHERE e.status = 'published'
              AND COALESCE(e.end_datetime, e.start_datetime) >= NOW()
            GROUP BY e.id
            ORDER BY e.start_datetime ASC";
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['categories'] = [];
        if (!empty($r['cat_pairs'])) {
            foreach (explode('||', $r['cat_pairs']) as $pair) {
                $parts = explode("\t", $pair);
                if (count($parts) === 2) {
                    $r['categories'][] = ['slug' => $parts[0], 'name' => $parts[1]];
                }
            }
        }
    }
    unset($r);
    return $rows;
}

/** Egy időszakkal átfedő, közzétett események (naptárhoz; múltbeli is). */
function fetchEventsBetween(PDO $pdo, string $from, string $to): array
{
    $sql = "SELECT e.*, r.name AS region_name, r.slug AS region_slug, r.image_url AS region_image_url,
                   GROUP_CONCAT(DISTINCT CONCAT(c.slug, '\\t', c.name) ORDER BY c.name SEPARATOR '||') AS cat_pairs
            FROM events e
            LEFT JOIN wine_regions r ON r.id = e.region_id
            LEFT JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN categories c ON c.id = ec.category_id
            WHERE e.status = 'published'
              AND e.start_datetime <= :to
              AND COALESCE(e.end_datetime, e.start_datetime) >= :from
            GROUP BY e.id
            ORDER BY e.start_datetime ASC";
    $st = $pdo->prepare($sql);
    $st->execute([':from' => $from, ':to' => $to]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['categories'] = [];
        if (!empty($r['cat_pairs'])) {
            foreach (explode('||', $r['cat_pairs']) as $pair) {
                $parts = explode("\t", $pair);
                if (count($parts) === 2) {
                    $r['categories'][] = ['slug' => $parts[0], 'name' => $parts[1]];
                }
            }
        }
    }
    unset($r);
    return $rows;
}

/** Borvidék + kategória (fazetta) szűrés — több érték is megadható (OR a fazettán belül). */
function applyFacets(array $events, array $regionSlugs, array $catSlugs): array
{
    if (!$regionSlugs && !$catSlugs) {
        return $events;
    }
    return array_values(array_filter($events, static function ($e) use ($regionSlugs, $catSlugs) {
        if ($regionSlugs && !in_array($e['region_slug'] ?? '', $regionSlugs, true)) {
            return false;
        }
        if ($catSlugs) {
            $slugs = array_map(static fn($c) => $c['slug'], $e['categories']);
            if (!array_intersect($catSlugs, $slugs)) {
                return false;
            }
        }
        return true;
    }));
}

/** Esemény kategória-nevei (megjelenítéshez). */
function categoryNames(array $e): array
{
    return array_map(static fn($c) => $c['name'], $e['categories']);
}

/** Kisbetűsít + magyar ékezetek eltávolítása (ékezet-érzéketlen kereséshez). */
function foldText(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
}

/** Szabadszavas keresés: név / helyszín / város / borvidék / kategória. */
function searchEvents(array $events, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return $events;
    }
    $needle = foldText($q);
    return array_values(array_filter($events, static function ($e) use ($needle) {
        $hay = $e['title'] . ' ' . ($e['venue_name'] ?? '') . ' ' . ($e['city'] ?? '') . ' ' . ($e['region_name'] ?? '');
        foreach ($e['categories'] as $c) {
            $hay .= ' ' . $c['name'];
        }
        return strpos(foldText($hay), $needle) !== false;
    }));
}

/** Kategória → [háttérszín, szövegszín] (naptári chipekhez, jelmagyarázathoz). */
const CAT_COLORS = [
    'borfesztival'       => ['#722f37', '#ffffff'], // burgundi
    'kostolo'            => ['#c8a14b', '#3a230f'], // arany
    'szureti-rendezveny' => ['#5a6b3b', '#ffffff'], // zöld
    'borvideki-program'  => ['#7a8450', '#ffffff'], // olíva
    'gasztronomia'       => ['#b5562a', '#ffffff'], // terrakotta
    'koncert'            => ['#8a4b6b', '#ffffff'], // szilva
    'csaladi-program'    => ['#9b6a2f', '#ffffff'], // borostyán
];
function categoryColorBySlug(string $slug): array
{
    return CAT_COLORS[$slug] ?? ['#722f37', '#ffffff'];
}
function categoryColor(array $e): array
{
    foreach ($e['categories'] as $c) {
        if (isset(CAT_COLORS[$c['slug']])) {
            return CAT_COLORS[$c['slug']];
        }
    }
    return ['#722f37', '#ffffff'];
}

/** Lista-URL építése a nézet + fazetták megőrzésével. */
function listUrl(string $view, array $regions = [], array $cats = [], string $sort = 'datum', string $q = ''): string
{
    $p = [];
    if ($view !== 'kozelgo') { $p['nezet'] = $view; }
    if ($regions)            { $p['borvidek'] = array_values($regions); }
    if ($cats)               { $p['kategoria'] = array_values($cats); }
    if ($sort !== 'datum')   { $p['rendezes'] = $sort; }
    if ($q !== '')           { $p['q'] = $q; }
    return 'esemenyek.php' . ($p ? ('?' . http_build_query($p)) : '');
}

/** Esemény részletoldalának URL-je (relatív vagy abszolút, ha base/dir adott). */
function eventUrl(array $e, string $base = '', string $dir = ''): string
{
    return ($base . $dir) . ($base ? '/' : '') . 'esemeny.php?slug=' . rawurlencode($e['slug']);
}

/** Egy esemény Schema.org Event objektuma (@context nélkül; ItemList-be vagy önállóan). */
function eventJsonLd(array $e, string $base, string $dir, ?string $url = null): array
{
    $img = $e['image_url'] ?? '';
    $imgAbs = $img ? ($base . $dir . '/' . ltrim($img, '/')) : null;
    $event = [
        '@type'               => 'Event',
        'name'                => $e['title'],
        'startDate'           => isoDate($e['start_datetime']),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'location'            => [
            '@type'   => 'Place',
            'name'    => $e['venue_name'] ?: ($e['city'] ?? ''),
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $e['address'] ?? '',
                'addressLocality' => $e['city'] ?? '',
                'addressCountry'  => 'HU',
            ],
        ],
    ];
    if (!empty($e['end_datetime']))      { $event['endDate'] = isoDate($e['end_datetime']); }
    if ($imgAbs)                         { $event['image'] = $imgAbs; }
    if (!empty($e['short_description'])) { $event['description'] = $e['short_description']; }
    if (!empty($e['latitude']) && !empty($e['longitude'])) {
        $event['location']['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $e['latitude'],
            'longitude' => (float) $e['longitude'],
        ];
    }
    if ((int) $e['is_free'] === 1) {
        $event['offers'] = ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'HUF',
                            'availability' => 'https://schema.org/InStock'];
    }
    if ($url) { $event['url'] = $url; }
    if (!empty($e['facebook_url'])) { $event['sameAs'] = [$e['facebook_url']]; }
    return $event;
}

/**
 * Schema.org ItemList + Event strukturált adat (SEO / AI-kereső).
 * Visszaad egy $jsonLd-be illeszthető tömböt, vagy null-t, ha nincs esemény.
 */
function eventsItemListJsonLd(array $events, string $base, string $dir, string $listName = 'Közelgő borrendezvények Magyarországon'): ?array
{
    $items = [];
    $pos = 1;
    foreach ($events as $e) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'item'     => eventJsonLd($e, $base, $dir, eventUrl($e, $base, $dir)),
        ];
    }
    if (!$items) {
        return null;
    }
    return [[
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => $listName,
        'itemListElement' => $items,
    ]];
}

/** Egy közzétett esemény lekérdezése slug alapján (címkékkel, borvidékkel). */
function fetchEventBySlug(PDO $pdo, string $slug): ?array
{
    $sql = "SELECT e.*, r.name AS region_name, r.slug AS region_slug, r.image_url AS region_image_url,
                   GROUP_CONCAT(DISTINCT CONCAT(c.slug, '\\t', c.name) ORDER BY c.name SEPARATOR '||') AS cat_pairs
            FROM events e
            LEFT JOIN wine_regions r ON r.id = e.region_id
            LEFT JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN categories c ON c.id = ec.category_id
            WHERE e.slug = :slug AND e.status = 'published'
            GROUP BY e.id
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':slug' => $slug]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['categories'] = [];
    if (!empty($r['cat_pairs'])) {
        foreach (explode('||', $r['cat_pairs']) as $pair) {
            $parts = explode("\t", $pair);
            if (count($parts) === 2) {
                $r['categories'][] = ['slug' => $parts[0], 'name' => $parts[1]];
            }
        }
    }
    return $r;
}

/** Egy esemény id alapján (BÁRMILYEN státusz), kategória-slug listával — adminhoz. */
function fetchEventByIdAdmin(PDO $pdo, int $id): ?array
{
    $sql = "SELECT e.*, GROUP_CONCAT(DISTINCT c.slug) AS cat_slugs
            FROM events e
            LEFT JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN categories c ON c.id = ec.category_id
            WHERE e.id = :id
            GROUP BY e.id
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['cat_slugs'] = !empty($r['cat_slugs']) ? explode(',', (string) $r['cat_slugs']) : [];
    return $r;
}

/** Státusz-pirula a dátumokból: Most zajlik / Utolsó napok / Hamarosan, vagy null. */
function eventStatus(string $start, ?string $end): ?array
{
    $now = new DateTimeImmutable('now');
    $s = new DateTimeImmutable($start);
    $e = $end ? new DateTimeImmutable($end) : $s;

    if ($s <= $now && $now <= $e) {
        $daysLeft = (int) $now->diff($e)->format('%a');
        if ($e > $s && $daysLeft <= 2) {
            return ['label' => 'Utolsó napok', 'class' => 'is-last'];
        }
        return ['label' => 'Most zajlik', 'class' => 'is-live'];
    }
    if ($s > $now) {
        $daysTo = (int) $now->diff($s)->format('%a');
        if ($daysTo <= 7) {
            return ['label' => 'Hamarosan', 'class' => 'is-soon'];
        }
    }
    return null;
}

/** Magyar dátum(tartomány): „2026. júl. 25–27.” / „2026. júl. 18.” */
function formatDateRange(string $start, ?string $end): string
{
    $s = new DateTimeImmutable($start);
    $sy = (int) $s->format('Y'); $sm = (int) $s->format('n'); $sd = (int) $s->format('j');

    if (!$end) {
        return "{$sy}. " . HU_MONTHS_SHORT[$sm] . " {$sd}.";
    }
    $e = new DateTimeImmutable($end);
    $ey = (int) $e->format('Y'); $em = (int) $e->format('n'); $ed = (int) $e->format('j');

    if ($sy === $ey && $sm === $em) {
        if ($sd === $ed) {
            return "{$sy}. " . HU_MONTHS_SHORT[$sm] . " {$sd}.";
        }
        return "{$sy}. " . HU_MONTHS_SHORT[$sm] . " {$sd}–{$ed}.";
    }
    if ($sy === $ey) {
        return "{$sy}. " . HU_MONTHS_SHORT[$sm] . " {$sd}. – " . HU_MONTHS_SHORT[$em] . " {$ed}.";
    }
    return "{$sy}. " . HU_MONTHS_SHORT[$sm] . " {$sd}. – {$ey}. " . HU_MONTHS_SHORT[$em] . " {$ed}.";
}

/** ISO 8601 dátum a <time datetime> és a JSON-LD számára (Europe/Budapest). */
function isoDate(string $dt): string
{
    return (new DateTimeImmutable($dt, new DateTimeZone('Europe/Budapest')))->format('c');
}

/** Hónap-csoport kulcs (rendezéshez) és felirat. */
function monthKey(string $start): string
{
    return (new DateTimeImmutable($start))->format('Y-m');
}
function monthLabel(string $start): string
{
    $s = new DateTimeImmutable($start);
    $m = (int) $s->format('n');
    $y = (int) $s->format('Y');
    $cur = (int) (new DateTimeImmutable('now'))->format('Y');
    return $y === $cur ? HU_MONTHS[$m] : (HU_MONTHS[$m] . " {$y}");
}
function monthDotColor(string $start): string
{
    $colors = ['#5a6b3b', '#c8a14b', '#b5562a', '#722f37', '#7a8450', '#9b6a2f'];
    $m = (int) (new DateTimeImmutable($start))->format('n');
    return $colors[$m % count($colors)];
}

/**
 * Lista-csoportok a rendezés szerint.
 * - dátum (asc/desc): hónapokra bontva (label = hónap)
 * - név: egyetlen lapos lista (label = null, nincs hónap-fejléc)
 * Visszaad: [['label'=>?string, 'dot'=>?string, 'events'=>array], ...]
 */
function groupEventsForList(array $events, string $sort): array
{
    if (!$events) {
        return [];
    }
    if ($sort === 'nev') {
        usort($events, static function ($a, $b) {
            return strcmp(mb_strtolower($a['title'], 'UTF-8'), mb_strtolower($b['title'], 'UTF-8'));
        });
        return [['label' => null, 'dot' => null, 'events' => $events]];
    }

    $byMonth = [];
    foreach ($events as $e) {
        $byMonth[monthKey($e['start_datetime'])][] = $e;
    }
    ksort($byMonth);
    if ($sort === 'datum-desc') {
        $byMonth = array_reverse($byMonth, true);
        foreach ($byMonth as $k => $evs) {
            $byMonth[$k] = array_reverse($evs);
        }
    }
    $groups = [];
    foreach ($byMonth as $evs) {
        $groups[] = [
            'label'  => monthLabel($evs[0]['start_datetime']),
            'dot'    => monthDotColor($evs[0]['start_datetime']),
            'events' => $evs,
        ];
    }
    return $groups;
}

/** A nap száma és rövid hónap a naptár-dátumkockához. */
function dayNumber(string $start): string
{
    return (new DateTimeImmutable($start))->format('j');
}
function shortMonthUpper(string $start): string
{
    $m = (int) (new DateTimeImmutable($start))->format('n');
    return mb_strtoupper(rtrim(HU_MONTHS_SHORT[$m], '.'), 'UTF-8');
}

/** Schema.org BreadcrumbList (SEO/AI morzsamenü). $items: [név => URL, …]. */
function breadcrumbJsonLd(array $items): array
{
    $list = [];
    $pos = 1;
    foreach ($items as $name => $url) {
        $entry = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $name];
        if ($url !== null && $url !== '') {
            $entry['item'] = $url;
        }
        $list[] = $entry;
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/** A megjelenítendő kép: esemény képe → borvidék képe → generikus hero. */
function eventImage(array $e): string
{
    if (!empty($e['image_url'])) {
        return $e['image_url'];
    }
    if (!empty($e['region_image_url'])) {
        return $e['region_image_url'];
    }
    return 'assets/hero.jpg';
}

/** Rövid HTML-escape segéd. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Slug képzése szövegből: kisbetű, ékezetek nélkül, kötőjelekkel (beküldéshez). */
function slugify(string $s): string
{
    $s = foldText($s); // kisbetű + magyar ékezetek eltávolítása
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/** Ütközésmentes esemény-slug: ha foglalt, -2, -3, … utótaggal. */
function uniqueEventSlug(PDO $pdo, string $base): string
{
    $base = $base !== '' ? $base : 'esemeny';
    $st = $pdo->prepare('SELECT 1 FROM events WHERE slug = ? LIMIT 1');
    $slug = $base;
    $i = 2;
    while (true) {
        $st->execute([$slug]);
        if (!$st->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

/** HTML datetime-local (vagy más felismerhető) → MySQL DATETIME, vagy null. */
function toMysqlDatetime(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($v))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/* =========================================================================
 *  Kattintás-naplózás (go.php) — kimenő kattintások az event_interactions-be
 * ========================================================================= */

/** A kimenő kattintás-átirányító URL-je (e=esemény id, t=típus: website|ticket). */
function goUrl(array $e, string $type): string
{
    return 'go.php?e=' . (int) $e['id'] . '&t=' . rawurlencode($type);
}

/** Titkos „só" az IP-hasheléshez (config.php → app_salt; van fallback). */
function appSalt(): string
{
    static $salt = null;
    if ($salt !== null) {
        return $salt;
    }
    $salt = '';
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) {
        $c = require $cfg;
        $salt = (string) ($c['app_salt'] ?? '');
    }
    if ($salt === '') {
        $salt = 'holborozzak-fallback-salt'; // ha nincs APP_SALT secret beállítva
    }
    return $salt;
}

/** Egyszerű bot-szűrő: ismert crawler/eszköz user agent vagy üres UA → ne számoljuk. */
function isLikelyBot(string $ua): bool
{
    if (trim($ua) === '') {
        return true;
    }
    return (bool) preg_match(
        '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|preview|monitor|curl|wget|python-requests|httpclient|headless|phantom|lighthouse/i',
        $ua
    );
}

/**
 * Egy kimenő kattintás naplózása. GDPR: nyers IP-t NEM tárolunk, csak napi sóval
 * hashelt értéket (napon belüli dedup-hoz, napok közt nem összeköthető). Botokat
 * nem számolunk. Soha nem dob kifelé — a hívó (go.php) így mindig át tud irányítani.
 */
function logInteraction(PDO $pdo, int $eventId, string $type): void
{
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    if (isLikelyBot($ua)) {
        return;
    }

    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '');
    if (strpos($ip, ',') !== false) {           // X-Forwarded-For: első a valódi kliens
        $ip = trim(explode(',', $ip)[0]);
    }

    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . appSalt() . '|' . date('Y-m-d')) : null;
    $referrer = isset($_SERVER['HTTP_REFERER'])
        ? substr((string) $_SERVER['HTTP_REFERER'], 0, 255)
        : null;

    $st = $pdo->prepare(
        'INSERT INTO event_interactions (event_id, type, referrer, ip_hash, user_agent)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$eventId, $type, $referrer, $ipHash, $ua !== '' ? $ua : null]);
}
