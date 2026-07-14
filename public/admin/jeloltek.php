<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../lib/events.php';
require __DIR__ . '/../lib/candidates.php';
require __DIR__ . '/../lib/ai.php';
require_admin();

$msg = '';
$err = '';
$report = []; // dedup: a kiszűrt duplikátumok tételes listája
$csrf = admin_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) {
        $err = 'Lejárt munkamenet. Töltsd újra az oldalt.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            $pdo = db();

            if ($action === 'import') {
                $url = trim((string) ($_POST['url'] ?? ''));
                $text = fetchUrlText($url);
                $d = aiExtractEvent($text, $url);

                if (empty($d['found']) || trim((string) ($d['title'] ?? '')) === '') {
                    $err = 'Az AI nem talált a szövegben konkrét borrendezvényt.';
                } else {
                    $title = trim((string) $d['title']);
                    $start = toMysqlDatetime((string) ($d['start_datetime'] ?? ''));
                    $end   = toMysqlDatetime((string) ($d['end_datetime'] ?? ''));
                    $city  = trim((string) ($d['city'] ?? ''));
                    $dedup = candidateDedupKey($title, $start, $city);

                    if (candidateDuplicate($pdo, $dedup) || eventDuplicate($pdo, $title, $start, $city)) {
                        $msg = 'Ez az esemény már szerepel (jelöltként vagy közzétéve), nem vettük fel újra.';
                    } else {
                        $st = $pdo->prepare(
                            "INSERT INTO event_candidates
                               (source_url, title, short_description, description, start_datetime, end_datetime,
                                venue_name, city, region_name, website_url, facebook_url, ticket_url,
                                is_free, price_info, image_url, dedup_key, status)
                             VALUES
                               (:src, :title, :short, :desc, :start, :end,
                                :venue, :city, :region, :web, :fb, :ticket,
                                :free, :price, :img, :dedup, 'new')"
                        );
                        $st->execute([
                            ':src'    => $url,
                            ':title'  => $title,
                            ':short'  => ($d['short_description'] ?? '') ?: null,
                            ':desc'   => ($d['description'] ?? '') ?: null,
                            ':start'  => $start,
                            ':end'    => $end,
                            ':venue'  => ($d['venue_name'] ?? '') ?: null,
                            ':city'   => $city !== '' ? $city : null,
                            ':region' => ($d['region_name'] ?? '') ?: null,
                            ':web'    => ($d['website_url'] ?? '') ?: null,
                            ':fb'     => ($d['facebook_url'] ?? '') ?: null,
                            ':ticket' => ($d['ticket_url'] ?? '') ?: null,
                            ':free'   => !empty($d['is_free']) ? 1 : 0,
                            ':price'  => ($d['price_info'] ?? '') ?: null,
                            ':img'    => ($d['image_url'] ?? '') ?: null,
                            ':dedup'  => $dedup,
                        ]);
                        $msg = 'Új jelölt felvéve: „' . $title . '”.';
                    }
                }
            } elseif ($action === 'approve') {
                $id = (int) ($_POST['id'] ?? 0);
                $c = $pdo->prepare('SELECT * FROM event_candidates WHERE id = ? AND status = "new" LIMIT 1');
                $c->execute([$id]);
                $cand = $c->fetch();
                if (!$cand) {
                    $err = 'A jelölt nem található.';
                } else {
                    $regionId = regionIdByName($pdo, $cand['region_name']);
                    $year = $cand['start_datetime'] ? (new DateTimeImmutable($cand['start_datetime']))->format('Y') : date('Y');
                    $slug = uniqueEventSlug($pdo, slugify($cand['title']) . '-' . $year);

                    $ins = $pdo->prepare(
                        "INSERT INTO events
                           (slug, title, short_description, description, start_datetime, end_datetime,
                            venue_name, city, region_id, website_url, facebook_url, ticket_url,
                            is_free, price_info, image_url, status)
                         VALUES
                           (:slug, :title, :short, :desc, :start, :end,
                            :venue, :city, :region_id, :web, :fb, :ticket,
                            :free, :price, :img, 'draft')"
                    );
                    $ins->execute([
                        ':slug'      => $slug,
                        ':title'     => $cand['title'],
                        ':short'     => $cand['short_description'],
                        ':desc'      => $cand['description'],
                        ':start'     => $cand['start_datetime'],
                        ':end'       => $cand['end_datetime'],
                        ':venue'     => $cand['venue_name'],
                        ':city'      => $cand['city'],
                        ':region_id' => $regionId,
                        ':web'       => $cand['website_url'],
                        ':fb'        => $cand['facebook_url'],
                        ':ticket'    => $cand['ticket_url'],
                        ':free'      => (int) $cand['is_free'],
                        ':price'     => $cand['price_info'],
                        ':img'       => $cand['image_url'],
                    ]);
                    $pdo->prepare('UPDATE event_candidates SET status = "approved" WHERE id = ?')->execute([$id]);
                    $msg = 'Jóváhagyva — draftként bekerült. Véglegesítsd a „Beérkezett" fülön (szerkesztés + közzététel).';
                }
            } elseif ($action === 'reject') {
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE event_candidates SET status = "rejected" WHERE id = ? AND status = "new"')->execute([$id]);
                $msg = 'Jelölt elvetve.';
            } elseif ($action === 'dedup') {
                // Duplikátumok kiszűrése: azonos (cím|nap|város) jelöltek közül a
                // legtöbb kitöltött mezővel rendelkezőt tartjuk meg; a már felvett
                // eseményekkel egyezők szintén duplikátumok. Nem véglegesen törlünk:
                // status='duplicate', így visszakereshető.
                $all = $pdo->query("SELECT * FROM event_candidates WHERE status = 'new' ORDER BY id ASC")->fetchAll();

                $fields = ['short_description', 'description', 'start_datetime', 'end_datetime',
                           'venue_name', 'city', 'region_name', 'website_url', 'facebook_url',
                           'ticket_url', 'image_url', 'price_info'];
                $score = static function (array $c) use ($fields): int {
                    $n = 0;
                    foreach ($fields as $f) {
                        if (!empty($c[$f])) { $n++; }
                    }
                    return $n;
                };
                $label = static fn (array $c): string => $c['title']
                    . ($c['start_datetime'] ? ' — ' . substr((string) $c['start_datetime'], 0, 10) : '')
                    . ($c['city'] ? ', ' . $c['city'] : '');

                // A kulcsot frissen számoljuk (a tárolt dedup_key régi soroknál hiányozhat)
                $groups = [];
                foreach ($all as $c) {
                    $key = candidateDedupKey((string) $c['title'], $c['start_datetime'] ?: null, $c['city'] ?: null);
                    $groups[$key][] = $c;
                }

                $dupIds = [];
                foreach ($groups as $g) {
                    if (count($g) < 2) {
                        continue;
                    }
                    usort($g, static fn ($a, $b) => [$score($b), (int) $a['id']] <=> [$score($a), (int) $b['id']]);
                    $best = array_shift($g);
                    foreach ($g as $d) {
                        $dupIds[] = (int) $d['id'];
                        $report[] = '„' . $label($d) . '” → megtartva helyette a teljesebb jelölt (#' . (int) $best['id'] . ')';
                    }
                }
                // A már felvett (draft/közzétett) eseményekkel egyező jelöltek is duplikátumok
                foreach ($all as $c) {
                    if (in_array((int) $c['id'], $dupIds, true)) {
                        continue;
                    }
                    if (eventDuplicate($pdo, (string) $c['title'], $c['start_datetime'] ?: null, $c['city'] ?: null)) {
                        $dupIds[] = (int) $c['id'];
                        $report[] = '„' . $label($c) . '” → már szerepel az események között';
                    }
                }

                if ($dupIds) {
                    $in = implode(',', array_fill(0, count($dupIds), '?'));
                    $pdo->prepare("UPDATE event_candidates SET status = 'duplicate' WHERE id IN ($in)")
                        ->execute($dupIds);
                    $msg = count($dupIds) . ' duplikátum kiszűrve (státusz: duplicate — nem végleges törlés).';
                } else {
                    $msg = 'Nem találtam duplikátumot a jelöltek között.';
                }
            }
        } catch (Throwable $e) {
            error_log('admin/jeloltek.php hiba: ' . $e->getMessage());
            $err = $e->getMessage();
        }
    }
}

// --- Automatikus import az /esemeny-gyujtes skill által deployolt fájlból ---
// A skill a public/admin/jelolt-import.json-t írja (commit → auto-deploy), az
// oldal megnyitásakor beolvassuk. A dedup-kulcs miatt idempotens: a már látott
// tételt (akár elvetett jelöltként) nem veszi fel újra — a fájlt nem kell törölni.
$importMsg = '';
$importFile = __DIR__ . '/jelolt-import.json';
if (is_file($importFile)) {
    try {
        $pdo = db();
        $data = json_decode((string) file_get_contents($importFile), true);
        $items = is_array($data['events'] ?? null) ? $data['events'] : [];
        $impAdded = 0;
        $impSkipped = 0;
        $ins = $pdo->prepare(
            "INSERT INTO event_candidates
               (source_url, title, short_description, description, start_datetime, end_datetime,
                venue_name, city, region_name, website_url, facebook_url, ticket_url,
                is_free, price_info, image_url, dedup_key, status)
             VALUES
               (:src, :title, :short, :desc, :start, :end,
                :venue, :city, :region, :web, :fb, :ticket,
                :free, :price, :img, :dedup, 'new')"
        );
        foreach ($items as $d) {
            if (!is_array($d)) {
                continue;
            }
            $title = trim((string) ($d['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $start = toMysqlDatetime((string) ($d['start_datetime'] ?? ''));
            $city  = trim((string) ($d['city'] ?? ''));
            $dedup = candidateDedupKey($title, $start, $city);
            if (candidateDuplicate($pdo, $dedup) || eventDuplicate($pdo, $title, $start, $city)) {
                $impSkipped++;
                continue;
            }
            $ins->execute([
                ':src'    => ($d['source_url'] ?? '') ?: null,
                ':title'  => $title,
                ':short'  => ($d['short_description'] ?? '') ?: null,
                ':desc'   => ($d['description'] ?? '') ?: null,
                ':start'  => $start,
                ':end'    => toMysqlDatetime((string) ($d['end_datetime'] ?? '')),
                ':venue'  => ($d['venue_name'] ?? '') ?: null,
                ':city'   => $city !== '' ? $city : null,
                ':region' => ($d['region_name'] ?? '') ?: null,
                ':web'    => ($d['website_url'] ?? '') ?: null,
                ':fb'     => ($d['facebook_url'] ?? '') ?: null,
                ':ticket' => ($d['ticket_url'] ?? '') ?: null,
                ':free'   => !empty($d['is_free']) ? 1 : 0,
                ':price'  => ($d['price_info'] ?? '') ?: null,
                ':img'    => ($d['image_url'] ?? '') ?: null,
                ':dedup'  => $dedup,
            ]);
            $impAdded++;
        }
        if ($impAdded > 0) {
            $importMsg = $impAdded . ' új jelölt érkezett a gyűjtő-fájlból'
                . ($impSkipped ? ' (' . $impSkipped . ' már ismert tételt kihagytunk)' : '') . '.';
        }
    } catch (Throwable $e) {
        error_log('admin/jeloltek.php import hiba: ' . $e->getMessage());
    }
}

// Jóváhagyásra váró jelöltek
$rows = [];
$counts = ['new' => 0, 'approved' => 0, 'rejected' => 0, 'duplicate' => 0];
try {
    $pdo = db();
    $rows = $pdo->query("SELECT * FROM event_candidates WHERE status = 'new' ORDER BY created_at DESC")->fetchAll();
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM event_candidates GROUP BY status") as $r) {
        $counts[$r['status']] = (int) $r['c'];
    }
} catch (Throwable $e) {
    error_log('admin/jeloltek.php lista hiba: ' . $e->getMessage());
}

$cssVer = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Jelöltek — admin</title>
  <link rel="stylesheet" href="../assets/style.css?v=<?= $cssVer ?>">
</head>
<body class="admin-body">
  <?php require __DIR__ . '/partials/nav.php'; ?>

  <main class="admin-main">
    <h1>Esemény-jelöltek</h1>
    <p class="admin-lead">
      Jóváhagyásra vár: <strong><?= (int) $counts['new'] ?></strong> ·
      Jóváhagyott: <strong><?= (int) $counts['approved'] ?></strong> ·
      Elvetett: <strong><?= (int) $counts['rejected'] ?></strong> ·
      Duplikátum: <strong><?= (int) $counts['duplicate'] ?></strong>
    </p>

    <?php if ($msg !== ''): ?>
      <div class="admin-msg">
        <?= h($msg) ?>
        <?php if ($report): ?>
          <ul class="admin-dedup-list">
            <?php foreach ($report as $line): ?><li><?= h($line) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($importMsg !== ''): ?><div class="admin-msg"><?= h($importMsg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="admin-error"><?= h($err) ?></div><?php endif; ?>

    <form method="post" action="jeloltek.php" class="admin-import">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="import">
      <label for="url">Import URL-ből (a Claude kinyeri az esemény adatait):</label>
      <div class="admin-import__row">
        <input type="url" id="url" name="url" placeholder="https://…" required>
        <button type="submit" class="btn btn--primary">Beolvasás</button>
      </div>
      <span class="admin-note">Tipp: egy konkrét esemény oldalát add meg (nem listaoldalt). A találat jelöltként jelenik meg lent.</span>
    </form>

    <form method="post" action="jeloltek.php" class="admin-actform admin-dedup-form"
          onsubmit="return confirm('Kiszűröd a duplikátumokat? (Nem végleges törlés: duplicate státuszt kapnak.)')">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="dedup">
      <button class="admin-btn" type="submit">Duplikátumok kiszűrése</button>
      <span class="admin-note">Azonos cím + nap + város jelöltekből a legteljesebbet tartja meg; a már
        felvett eseményekkel egyezőket is kiszűri. Az eredmény tételesen megjelenik.</span>
    </form>

    <?php if (!$rows): ?>
      <div class="admin-empty">Jelenleg nincs jóváhagyásra váró jelölt.</div>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Esemény</th><th>Időpont</th><th>Helyszín</th><th>Forrás</th><th>Műveletek</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
            <tr>
              <td>
                <strong><?= h($r['title']) ?></strong>
                <?php if (!empty($r['short_description'])): ?><br><span class="admin-sub"><?= h($r['short_description']) ?></span><?php endif; ?>
                <?php if (!empty($r['region_name'])): ?><br><span class="admin-sub">🍇 <?= h($r['region_name']) ?></span><?php endif; ?>
              </td>
              <td><?= $r['start_datetime'] ? h(formatDateRange($r['start_datetime'], $r['end_datetime'])) : '—' ?></td>
              <td><?= h(trim(($r['venue_name'] ? $r['venue_name'] . ', ' : '') . ($r['city'] ?? ''))) ?: '—' ?></td>
              <td><?php if (!empty($r['source_url'])): ?><a href="<?= h($r['source_url']) ?>" target="_blank" rel="noopener noreferrer">forrás ↗</a><?php else: ?>—<?php endif; ?></td>
              <td class="admin-actions-cell">
                <a class="admin-link" href="jelolt-preview.php?id=<?= $id ?>" target="_blank" rel="noopener">Előnézet ↗</a>
                <form method="post" action="jeloltek.php" class="admin-actform">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="admin-btn admin-btn--go" type="submit">Jóváhagyás</button>
                </form>
                <form method="post" action="jeloltek.php" class="admin-actform" onsubmit="return confirm('Biztosan elveted?')">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="admin-btn admin-btn--danger" type="submit">Elvetés</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="admin-note">A jóváhagyott jelölt <strong>draft</strong> eseményként kerül be — a „Beérkezett" fülön szerkeszd/véglegesítsd (kép, koordináta, közzététel).</p>
    <?php endif; ?>
  </main>
</body>
</html>
