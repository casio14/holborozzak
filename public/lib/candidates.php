<?php
declare(strict_types=1);

/**
 * Esemény-jelölt segédfüggvények: dedup-kulcs, ütközésvizsgálat, borvidék-mappelés.
 * Igényli: lib/events.php (foldText, toMysqlDatetime, slugify, uniqueEventSlug).
 */

require_once __DIR__ . '/events.php';

/** Normalizált kulcs az ismétlődés-szűréshez: ékezettelen cím | nap | város. */
function candidateDedupKey(string $title, ?string $start, ?string $city): string
{
    $day = '';
    if (!empty($start)) {
        try {
            $day = (new DateTimeImmutable($start))->format('Y-m-d');
        } catch (Throwable $e) {
            $day = '';
        }
    }
    return foldText($title) . '|' . $day . '|' . foldText((string) $city);
}

/** Szerepel-e már ugyanez a jelölt a jelöltek táblában? */
function candidateDuplicate(PDO $pdo, string $dedupKey): bool
{
    $st = $pdo->prepare('SELECT 1 FROM event_candidates WHERE dedup_key = ? LIMIT 1');
    $st->execute([$dedupKey]);
    return (bool) $st->fetchColumn();
}

/** Szerepel-e már az events táblában (azonos nap + város + ékezettelen cím)? */
function eventDuplicate(PDO $pdo, string $title, ?string $start, ?string $city): bool
{
    if (empty($start)) {
        return false;
    }
    try {
        $day = (new DateTimeImmutable($start))->format('Y-m-d');
    } catch (Throwable $e) {
        return false;
    }
    $city = trim((string) $city);
    $sql = 'SELECT title FROM events WHERE DATE(start_datetime) = ?';
    $params = [$day];
    if ($city !== '') {
        $sql .= ' AND city = ?';
        $params[] = $city;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $needle = foldText($title);
    foreach ($st->fetchAll() as $r) {
        if (foldText((string) $r['title']) === $needle) {
            return true;
        }
    }
    return false;
}

/** Borvidék-név → wine_regions.id (ékezet-érzéketlen, részleges egyezés). */
function regionIdByName(PDO $pdo, ?string $name): ?int
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }
    $needle = foldText($name);
    foreach ($pdo->query('SELECT id, name FROM wine_regions') as $r) {
        $rn = foldText((string) $r['name']);
        if ($rn === $needle || strpos($rn, $needle) !== false || strpos($needle, $rn) !== false) {
            return (int) $r['id'];
        }
    }
    return null;
}
