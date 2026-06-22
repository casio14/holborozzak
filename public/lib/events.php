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
    $sql = "SELECT e.*, r.name AS region_name,
                   GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '||') AS cat_names
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
        $r['categories'] = !empty($r['cat_names']) ? explode('||', $r['cat_names']) : [];
    }
    return $rows;
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

/** Rövid HTML-escape segéd. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
