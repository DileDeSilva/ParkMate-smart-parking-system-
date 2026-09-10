<?php
/**
 * ParkMate - car park detail, bay picker and booking form
 */

require_once __DIR__ . '/config/config.php';

$lotId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
[$start, $end] = requested_window();
$hours = billed_hours($start, $end);

$stmt = db()->prepare(
    'SELECT pl.*, c.city_name, u.full_name AS owner_name
       FROM parking_lots pl
       JOIN cities c ON c.city_id = pl.city_id
       JOIN users  u ON u.user_id = pl.owner_id
      WHERE pl.lot_id = ?'
);
$stmt->execute([$lotId]);
$lot = $stmt->fetch();

if (!$lot || $lot['status'] !== 'active') {
    http_response_code(404);
    flash('error', 'That car park is not available.');
    redirect('search.php');
}

$slots = slots_for_window($lotId, $start, $end);
$freeCount = count(array_filter($slots, fn($s) => $s['is_free']));

// Group the bays by floor so the deck reads like the real building.
$byFloor = [];
foreach ($slots as $slot) {
    $byFloor[$slot['floor_level']][] = $slot;
}

// Vehicles belonging to the signed-in customer, for the booking form.
$vehicles = [];
if (is_logged_in() && user_role() === 'customer') {
    $vs = db()->prepare(
        'SELECT vehicle_id, plate_number, make_model
           FROM vehicles WHERE user_id = ? ORDER BY plate_number'
    );
    $vs->execute([user_id()]);
    $vehicles = $vs->fetchAll();
}

$rv = db()->prepare(
    'SELECT r.rating, r.comment, r.created_at, u.full_name
       FROM reviews r JOIN users u ON u.user_id = r.user_id
      WHERE r.lot_id = ? ORDER BY r.created_at DESC LIMIT 6'
);
$rv->execute([$lotId]);
$reviews = $rv->fetchAll();

$avg = db()->prepare('SELECT ROUND(AVG(rating),1) FROM reviews WHERE lot_id = ?');
$avg->execute([$lotId]);
$avgRating = $avg->fetchColumn();

// Warn if the window falls outside opening hours.
$outsideHours = date('H:i:s', strtotime($start)) < $lot['opening_time']
             || date('H:i:s', strtotime($end))   > $lot['closing_time'];

$pageTitle = $lot['lot_name'];
$navKey    = 'search';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1><?= e($lot['lot_name']) ?></h1>
                <p class="muted">
                    <?= e($lot['address']) ?> &middot; <?= e($lot['city_name']) ?>
                    &middot; open <?= e(tm($lot['opening_time'])) ?>&ndash;<?= e(tm($lot['closing_time'])) ?>
                    <?php if ($avgRating !== false && $avgRating !== null): ?>
                        &middot; rated <?= e((string) $avgRating) ?>/5
                    <?php endif; ?>
                </p>
            </div>
            <a class="btn btn--ghost" href="<?= e(url('search.php?start=' . urlencode($start) . '&end=' . urlencode($end))) ?>">
                Back to results
            </a>
        </div>

        <?php if ($lot['description']): ?>
            <p><?= e($lot['description']) ?></p>
        <?php endif; ?>

        <!-- Change the window without leaving the page -->
        <form class="finder finder--inline" method="get" action="<?= e(url('lot.php')) ?>">
            <input type="hidden" name="id" value="<?= $lotId ?>">
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
            <div class="field">
                <span>Length of stay</span>
                <strong style="display:block;padding:9px 0">
                    <?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?>
                </strong>
            </div>
            <button class="btn" type="submit">Check this window</button>
        </form>

        <?php if ($outsideHours): ?>
            <div class="notice notice--info">
                This car park is open
                <?= e(tm($lot['opening_time'])) ?>&ndash;<?= e(tm($lot['closing_time'])) ?>.
                Part of your window falls outside those hours, so you may not be able
                to get in or out.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('reserve.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="lot_id" value="<?= $lotId ?>">
            <input type="hidden" name="start" value="<?= e($start) ?>">
            <input type="hidden" name="end"   value="<?= e($end) ?>">
            <input type="hidden" name="hours" value="<?= $hours ?>" data-hours>

            <div class="grid grid--sidebar">

                <!-- ===== The deck ===== -->
                <section>
                    <div class="card__head">
                        <h2>Choose your bay</h2>
                        <span class="muted small">
                            <?= $freeCount ?> of <?= count($slots) ?> free for this window
                        </span>
                    </div>

                    <div class="deck" data-deck>
                        <?php foreach ($byFloor as $floor => $floorSlots): ?> //Show available parking bays
                            <p class="deck__floor"><?= e($floor) ?></p>
                            <div class="bays">
                                <?php foreach ($floorSlots as $slot):
                                    if ($slot['status'] !== 'available') {
                                        $class = 'bay bay--closed';
                                        $note  = 'Closed';
                                    } elseif (!$slot['is_free']) {
                                        $class = 'bay bay--taken';
                                        $note  = 'Booked';
                                    } else {
                                        $class = 'bay bay--free';
                                        $note  = money($slot['hourly_rate']);
                                    }
                                ?>
                                    <label class="<?= $class ?>">
                                        <?php if ($slot['is_free']): ?>
                                            <input type="radio" name="slot_id"
                                                   value="<?= (int) $slot['slot_id'] ?>"
                                                   data-rate="<?= e((string) $slot['hourly_rate']) ?>"
                                                   data-code="<?= e($slot['slot_code']) ?>">
                                        <?php endif; ?>
                                        <span class="bay__code"><?= e($slot['slot_code']) ?></span>
                                        <span class="bay__type"><?= e($slot['type_name']) ?></span>
                                        <span class="bay__rate"><?= e($note) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="legend">
                            <span><i class="is-free"></i> Free</span>
                            <span><i class="is-picked"></i> Your choice</span>
                            <span><i class="is-taken"></i> Already booked</span>
                            <span><i class="is-closed"></i> Out of service</span>
                        </div>
                    </div>
                </section>

                <!-- ===== Summary rail ===== -->
                <aside>
                    <div class="summary">
                        <h3>Your booking</h3>
                        <dl>
                            <dt>Car park</dt><dd><?= e($lot['lot_name']) ?></dd>
                            <dt>Arriving</dt><dd><?= e(dt($start)) ?></dd>
                            <dt>Leaving</dt><dd><?= e(dt($end)) ?></dd>
                            <dt>Charged</dt><dd><?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?></dd>
                            <dt>Bay</dt><dd data-out="bay">Not chosen</dd>
                            <dt>Rate</dt><dd data-out="rate">&mdash;</dd>
                        </dl>

                        <div class="summary__total">
                            <span>Total</span>
                            <b data-out="total">&mdash;</b>
                        </div>

                        <?php if (!is_logged_in()): ?>
                            <p class="small" style="color:#b9c0c8;margin:16px 0 10px">
                                Sign in to hold this bay.
                            </p>
                            <a class="btn btn--primary btn--block" href="<?= e(url('login.php')) ?>">
                                Sign in to book
                            </a>

                        <?php elseif (user_role() !== 'customer'): ?>
                            <p class="small" style="color:#b9c0c8;margin:16px 0 0">
                                Bookings are made from a customer account.
                            </p>

                        <?php elseif (!$vehicles): ?>
                            <p class="small" style="color:#b9c0c8;margin:16px 0 10px">
                                Add a vehicle first so we know which plate to expect.
                            </p>
                            <a class="btn btn--primary btn--block" href="<?= e(url('vehicles.php')) ?>">
                                Add a vehicle
                            </a>

                        <?php else: ?>
                            <label class="field" style="margin:16px 0 12px">
                                <span style="color:#9aa2ab">Vehicle</span>
                                <select name="vehicle_id" required>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= (int) $v['vehicle_id'] ?>">
                                            <?= e($v['plate_number']) ?>
                                            <?= $v['make_model'] ? ' - ' . e($v['make_model']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <button class="btn btn--primary btn--block" type="submit"
                                    data-book-submit disabled>
                                Hold this bay
                            </button>
                            <p class="small" style="color:#8b939c;margin:10px 0 0">
                                You pay on the next screen. Nothing is charged yet.
                            </p>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </form>

        <!-- ===== Reviews ===== -->
        <section class="card" style="margin-top:32px">
            <div class="card__head">
                <h2>What drivers said</h2>
                <?php if ($avgRating !== false && $avgRating !== null): ?>
                    <span class="muted small"><?= e((string) $avgRating) ?> out of 5</span>
                <?php endif; ?>
            </div>

            <?php if (!$reviews): ?>
                <p class="muted">No reviews yet. Park here and you can leave the first one.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review">
                        <div class="review__head">
                            <span class="review__name"><?= e($review['full_name']) ?></span>
                            <span class="stars" aria-label="<?= (int) $review['rating'] ?> out of 5">
                                <?= str_repeat('&#9733;', (int) $review['rating'])
                                  . str_repeat('&#9734;', 5 - (int) $review['rating']) ?>
                            </span>
                        </div>
                        <?php if ($review['comment']): ?>
                            <p class="muted small" style="margin:4px 0 0"><?= e($review['comment']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
