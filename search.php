<?php
/**
 * ParkMate - search car parks for a booking window
 */

require_once __DIR__ . '/config/config.php';

[$start, $end] = requested_window();
$cityId   = isset($_GET['city_id']) && $_GET['city_id'] !== '' ? (int) $_GET['city_id'] : null;
$onlyFree = isset($_GET['only_free']);
$hours    = billed_hours($start, $end);

$cities = db()->query(
    'SELECT city_id, city_name FROM cities ORDER BY city_name'
)->fetchAll();

// Lots matching the city filter, then availability worked out for the window.
$sql = 'SELECT pl.lot_id, pl.lot_name, pl.address, pl.opening_time, pl.closing_time,
               c.city_name,
               COUNT(ps.slot_id) AS total_slots,
               MIN(ps.hourly_rate) AS min_rate,
               ROUND(AVG(rv.rating), 1) AS avg_rating,
               COUNT(DISTINCT rv.review_id) AS review_count
          FROM parking_lots pl
          JOIN cities c ON c.city_id = pl.city_id
     LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
     LEFT JOIN reviews rv ON rv.lot_id = pl.lot_id
         WHERE pl.status = "active"';

$params = [];
if ($cityId !== null) {
    $sql .= ' AND pl.city_id = :city_id';
    $params['city_id'] = $cityId;
}
$sql .= ' GROUP BY pl.lot_id, pl.lot_name, pl.address, pl.opening_time,
                   pl.closing_time, c.city_name
          ORDER BY pl.lot_name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$lots = $stmt->fetchAll();

$freeCounts = free_counts_for_window($start, $end);

if ($onlyFree) {
    $lots = array_filter($lots, fn($lot) => ($freeCounts[(int) $lot['lot_id']] ?? 0) > 0);
}

$pageTitle = 'Find parking';
$navKey    = 'search';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Find parking</h1>
                <p class="muted">
                    Availability shown for <?= e(dt($start)) ?> to <?= e(dt($end)) ?>
                    &mdash; <?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?>.
                </p>
            </div>
        </div>

        <form class="finder finder--inline" method="get" action="<?= e(url('search.php')) ?>">
            <label class="field">
                <span>Area</span>
                <select name="city_id">
                    <option value="">Anywhere</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= (int) $city['city_id'] ?>"
                            <?= $cityId === (int) $city['city_id'] ? 'selected' : '' ?>>
                            <?= e($city['city_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Arriving</span>
                <input type="datetime-local" name="start"
                       value="<?= e(input_dt($start)) ?>" data-window-start required>
            </label>

            <label class="field">
                <span>Leaving</span>
                <input type="datetime-local" name="end"
                       value="<?= e(input_dt($end)) ?>" data-window-end required>
            </label>

            <button class="btn btn--primary" type="submit">Update results</button>

            <label class="check" style="grid-column:1/-1">
                <input type="checkbox" name="only_free" value="1" <?= $onlyFree ? 'checked' : '' ?>>
                <span>Hide car parks with nothing free for this window</span>
            </label>
        </form>

        <?php if (!$lots): ?>
            <div class="empty">
                <h3>Nothing matches that search</h3>
                <p class="muted">Try a wider area, or shift your arrival time by an hour.</p>
            </div>
        <?php else: ?>
            <p class="muted small"><?= count($lots) ?> car <?= count($lots) === 1 ? 'park' : 'parks' ?> found.</p>

            <div class="stack">
                <?php foreach ($lots as $lot):
                    $lotId = (int) $lot['lot_id'];
                    $free  = $freeCounts[$lotId] ?? 0;
                    $total = (int) $lot['total_slots'];
                    $ratio = $total > 0 ? $free / $total : 0;
                    $tone  = $free === 0 ? 'lot--full' : ($ratio < 0.25 ? 'lot--tight' : '');
                    $link  = url('lot.php?id=' . $lotId
                             . '&start=' . urlencode($start) . '&end=' . urlencode($end));
                ?>
                    <article class="lot <?= $tone ?>">
                        <div class="lot__main">
                            <h3 class="lot__name"><?= e($lot['lot_name']) ?></h3>
                            <p class="lot__meta">
                                <?= e($lot['address']) ?>
                            </p>
                            <p class="lot__meta">
                                Open <?= e(tm($lot['opening_time'])) ?>&ndash;<?= e(tm($lot['closing_time'])) ?>
                                &middot; from <?= e(money($lot['min_rate'])) ?> an hour
                                <?php if ($lot['avg_rating'] !== null): ?>
                                    &middot; rated <?= e((string) $lot['avg_rating']) ?>/5
                                    from <?= (int) $lot['review_count'] ?>
                                    <?= (int) $lot['review_count'] === 1 ? 'review' : 'reviews' ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="lot__count">
                            <?= $free ?><small>of <?= $total ?> free</small>
                        </div>

                        <?php if ($free > 0): ?>
                            <a class="btn btn--primary" href="<?= e($link) ?>">Choose a bay</a>
                        <?php else: ?>
                            <a class="btn btn--ghost" href="<?= e($link) ?>">Try another time</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
