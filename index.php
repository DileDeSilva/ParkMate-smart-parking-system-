<?php
/**
 * ParkMate - landing page
 */

require_once __DIR__ . '/config/config.php';

// Signed-in staff belong on their own dashboards.
if (is_logged_in() && user_role() !== 'customer') {
    redirect(home_for_role(user_role()));
}

[$start, $end] = requested_window();

$cities = db()->query(
    'SELECT c.city_id, c.city_name
       FROM cities c
       JOIN parking_lots pl ON pl.city_id = c.city_id AND pl.status = "active"
   GROUP BY c.city_id, c.city_name
   ORDER BY c.city_name'
)->fetchAll();

$lots = db()->query(
    'SELECT * FROM vw_lot_availability
      WHERE lot_status = "active"
   ORDER BY free_slots DESC, lot_name
      LIMIT 4'
)->fetchAll();

$totals = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM parking_lots  WHERE status = "active")    AS lots,
        (SELECT COUNT(*) FROM parking_slots WHERE status = "available") AS bays,
        (SELECT COUNT(DISTINCT city_id) FROM parking_lots)              AS cities'
)->fetch();

$pageTitle = 'Reserve a parking bay';
$navKey    = 'home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="shell hero__inner">
        <h1>Know your bay is waiting before you set off.</h1>
        <p>
            Pick a car park, choose the exact bay, and pay for the hours you need.
            No circling the block, no hunting for a space on arrival.
        </p>

        <form class="finder" action="<?= e(url('search.php')) ?>" method="get">
            <label class="field">
                <span>Where are you going?</span>
                <select name="city_id">
                    <option value="">Anywhere</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= (int) $city['city_id'] ?>">
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

            <button class="btn btn--primary" type="submit">Show free bays</button>
        </form>
    </div>
</section>

<main class="page">
    <div class="shell">

        <div class="grid grid--3" style="margin-bottom:36px">
            <div class="stat stat--free">
                <span class="stat__value"><?= (int) $totals['bays'] ?></span>
                <span class="stat__label">bays you can book right now</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $totals['lots'] ?></span>
                <span class="stat__label">car parks on ParkMate</span>
            </div>
            <div class="stat stat--money">
                <span class="stat__value"><?= (int) $totals['cities'] ?></span>
                <span class="stat__label">towns and city districts covered</span>
            </div>
        </div>

        <div class="page-head">
            <div>
                <h2>Free bays at the moment</h2>
                <p class="muted">Counts update as bookings come in.</p>
            </div>
            <a class="btn btn--ghost" href="<?= e(url('search.php')) ?>">See all car parks</a>
        </div>

        <div class="stack">
            <?php foreach ($lots as $lot):
                $free  = (int) $lot['free_slots'];
                $total = (int) $lot['total_slots'];
                $ratio = $total > 0 ? $free / $total : 0;
                $tone  = $free === 0 ? 'lot--full' : ($ratio < 0.25 ? 'lot--tight' : '');
            ?>
                <article class="lot <?= $tone ?>">
                    <div class="lot__main">
                        <h3 class="lot__name"><?= e($lot['lot_name']) ?></h3>
                        <p class="lot__meta">
                            <?= e($lot['city_name']) ?> &middot;
                            open <?= e(tm($lot['opening_time'])) ?>&ndash;<?= e(tm($lot['closing_time'])) ?>
                            &middot; from <?= e(money($lot['min_rate'])) ?> an hour
                        </p>
                    </div>

                    <div class="lot__count">
                        <?= $free ?><small>of <?= $total ?> free</small>
                    </div>

                    <a class="btn btn--primary"
                       href="<?= e(url('lot.php?id=' . (int) $lot['lot_id']
                            . '&start=' . urlencode($start) . '&end=' . urlencode($end))) ?>">
                        Choose a bay
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="grid grid--3" style="margin-top:48px">
            <div class="card">
                <h3>Pick the exact bay</h3>
                <p class="muted">
                    You see the car park laid out floor by floor and choose the space
                    yourself, so you know where you are heading before you arrive.
                </p>
            </div>
            <div class="card">
                <h3>Pay for the hours you book</h3>
                <p class="muted">
                    Rates are set per bay and charged by the hour. The price is shown
                    in full before you confirm anything.
                </p>
            </div>
            <div class="card">
                <h3>Operators keep control</h3>
                <p class="muted">
                    Car park owners manage their own bays, rates and opening hours,
                    and watch bookings arrive on a live dashboard.
                </p>
            </div>
        </div>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
