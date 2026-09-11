<?php
/**
 * ParkMate - a customer's bookings
 */

require_once __DIR__ . '/config/config.php';

require_role('customer');
$uid = user_id();

// Leaving a review for a car park you have actually used.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_verify();

    $lotId  = (int) ($_POST['lot_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $text   = trim($_POST['comment'] ?? '');

    $used = db()->prepare(
        'SELECT COUNT(*) FROM reservations r
           JOIN parking_slots ps ON ps.slot_id = r.slot_id
          WHERE r.user_id = ? AND ps.lot_id = ? AND r.status = "completed"'
    );
    $used->execute([$uid, $lotId]);

    if ($rating < 1 || $rating > 5) {
        flash('error', 'Give a rating between 1 and 5.');
    } elseif ((int) $used->fetchColumn() === 0) {
        flash('error', 'You can review a car park once you have completed a stay there.');
    } else {
        // One review per person per car park; a second submission edits it.
        $save = db()->prepare(
            'INSERT INTO reviews (lot_id, user_id, rating, comment)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)'
        );
        $save->execute([$lotId, $uid, $rating, $text !== '' ? $text : null]);
        flash('success', 'Thanks, your review is posted.');
    }
    redirect('my-bookings.php');
}

// Close out anything whose window has passed.
$sweep = db()->prepare(
    'UPDATE reservations SET status = "completed"
      WHERE user_id = ? AND status IN ("confirmed","active") AND end_time < NOW()'
);
$sweep->execute([$uid]);

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

$sql = 'SELECT * FROM vw_reservation_details WHERE user_id = :uid';
$params = ['uid' => $uid];
if ($filter !== 'all') {
    $sql .= ' AND reservation_status = :status';
    $params['status'] = $filter;
}
$sql .= ' ORDER BY start_time DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Which completed car parks still need a review from this person?
$reviewable = db()->prepare(
    'SELECT DISTINCT pl.lot_id, pl.lot_name
       FROM reservations r
       JOIN parking_slots ps ON ps.slot_id = r.slot_id
       JOIN parking_lots  pl ON pl.lot_id  = ps.lot_id
      WHERE r.user_id = ? AND r.status = "completed"
        AND NOT EXISTS (SELECT 1 FROM reviews rv
                         WHERE rv.lot_id = pl.lot_id AND rv.user_id = r.user_id)'
);
$reviewable->execute([$uid]);
$toReview = $reviewable->fetchAll();

$pageTitle = 'My bookings';
$navKey    = 'bookings';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>My bookings</h1>
                <p class="muted">Every bay you have held, past and future.</p>
            </div>
            <a class="btn btn--primary" href="<?= e(url('search.php')) ?>">Book another</a>
        </div>

        <div class="row" style="margin-bottom:18px">
            <?php foreach ($allowed as $option): ?>
                <a class="btn btn--small <?= $filter === $option ? '' : 'btn--ghost' ?>"
                   href="<?= e(url('my-bookings.php?status=' . $option)) ?>">
                    <?= e($option === 'all' ? 'Everything' : ucfirst($option)) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!$bookings): ?>
            <div class="empty">
                <h3>Nothing here</h3>
                <p>No bookings match that filter.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Car park</th>
                            <th>Bay</th>
                            <th>Vehicle</th>
                            <th>Window</th>
                            <th class="num">Amount</th>
                            <th>Booking</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?= (int) $b['reservation_id'] ?></td>
                                <td>
                                    <strong><?= e($b['lot_name']) ?></strong><br>
                                    <span class="muted small"><?= e($b['city_name']) ?></span>
                                </td>
                                <td>
                                    <span class="code"><?= e($b['slot_code']) ?></span><br>
                                    <span class="muted small"><?= e($b['floor_level']) ?></span>
                                </td>
                                <td><?= e($b['plate_number']) ?></td>
                                <td class="small">
                                    <?= e(dt($b['start_time'])) ?><br>
                                    <span class="muted">to <?= e(dt($b['end_time'])) ?></span>
                                </td>
                                <td class="num"><?= e(money($b['total_amount'])) ?></td>
                                <td>
                                    <span class="<?= e(status_class($b['reservation_status'])) ?>">
                                        <?= e(ucfirst($b['reservation_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?= e(status_class((string) $b['payment_status'])) ?>">
                                        <?= e(ucfirst((string) $b['payment_status'])) ?>
                                    </span>
                                </td>
                                <td class="nowrap">
                                    <?php if ($b['payment_status'] === 'pending'
                                              && $b['reservation_status'] === 'pending'): ?>
                                        <a class="btn btn--small btn--primary"
                                           href="<?= e(url('payment.php?reservation_id=' . (int) $b['reservation_id'])) ?>">Pay</a>
                                    <?php endif; ?>

                                    <?php if (in_array($b['reservation_status'], ['pending','confirmed'], true)
                                              && strtotime($b['start_time']) > time()): ?>
                                        <a class="btn btn--small btn--ghost"
                                           href="<?= e(url('cancel.php?reservation_id=' . (int) $b['reservation_id'])) ?>"
                                           data-confirm="Cancel this booking? Any payment will be refunded.">Cancel</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($toReview): ?>
            <section class="card" style="margin-top:28px">
                <h2>Rate a car park you used</h2>
                <p class="muted small">
                    Your rating helps other drivers pick well. You can update it later.
                </p>

                <form method="post" class="row" style="align-items:flex-end;gap:12px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review">

                    <label class="field" style="flex:1 1 240px">
                        <span>Car park</span>
                        <select name="lot_id" required>
                            <?php foreach ($toReview as $lot): ?>
                                <option value="<?= (int) $lot['lot_id'] ?>"><?= e($lot['lot_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field" style="flex:0 0 130px">
                        <span>Rating</span>
                        <select name="rating" required>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> out of 5</option>
                            <?php endfor; ?>
                        </select>
                    </label>

                    <label class="field" style="flex:2 1 300px">
                        <span>Anything worth mentioning?</span>
                        <input type="text" name="comment" maxlength="400"
                               placeholder="Easy to find, lift right beside the bay">
                    </label>

                    <button class="btn btn--primary" type="submit">Post review</button>
                </form>
            </section>
        <?php endif; ?>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
