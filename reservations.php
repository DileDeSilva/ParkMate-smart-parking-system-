<?php
/**
 * ParkMate - bookings across an owner's car parks
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('owner', 'admin');
$ownerId = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);

    // The booking has to sit in one of this owner's car parks.
    $check = db()->prepare(
        'SELECT r.status FROM reservations r
           JOIN parking_slots ps ON ps.slot_id = r.slot_id
           JOIN parking_lots  pl ON pl.lot_id  = ps.lot_id
          WHERE r.reservation_id = ? AND pl.owner_id = ?'
    );
    $check->execute([$reservationId, $ownerId]);
    $status = $check->fetchColumn();

    if ($status === false) {
        http_response_code(403);
        flash('error', 'That booking is not in one of your car parks.');
        redirect('owner/reservations.php');
    }

    if ($action === 'checkin' && $status === 'confirmed') {
        db()->prepare('UPDATE reservations SET status = "active" WHERE reservation_id = ?')
            ->execute([$reservationId]);
        flash('success', 'Vehicle checked in.');
    }

    if ($action === 'checkout' && in_array($status, ['active', 'confirmed'], true)) {
        db()->prepare('UPDATE reservations SET status = "completed" WHERE reservation_id = ?')
            ->execute([$reservationId]);
        flash('success', 'Stay closed off. The bay is free again.');
    }

    redirect('owner/reservations.php');
}

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'confirmed', 'active', 'completed', 'cancelled'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

$sql = 'SELECT * FROM vw_reservation_details WHERE owner_id = :owner';
$params = ['owner' => $ownerId];

if ($filter !== 'all') {
    $sql .= ' AND reservation_status = :status';
    $params['status'] = $filter;
}
$sql .= ' ORDER BY start_time DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Bookings';
$navKey    = 'o-res';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Bookings</h1>
                <p class="muted">Everything booked across your car parks.</p>
            </div>
        </div>

        <div class="row" style="margin-bottom:18px">
            <?php foreach ($allowed as $option): ?>
                <a class="btn btn--small <?= $filter === $option ? '' : 'btn--ghost' ?>"
                   href="<?= e(url('owner/reservations.php?status=' . $option)) ?>">
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
                            <th>Customer</th>
                            <th>Car park</th>
                            <th>Bay</th>
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
                                    <?= e($b['customer_name']) ?><br>
                                    <span class="muted small">
                                        <?= e($b['plate_number']) ?> &middot; <?= e($b['customer_phone']) ?>
                                    </span>
                                </td>
                                <td class="small"><?= e($b['lot_name']) ?></td>
                                <td><span class="code"><?= e($b['slot_code']) ?></span></td>
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
                                    <?php if ($b['reservation_status'] === 'confirmed'): ?>
                                        <form method="post" style="display:inline;margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="checkin">
                                            <input type="hidden" name="reservation_id" value="<?= (int) $b['reservation_id'] ?>">
                                            <button class="btn btn--small btn--primary" type="submit">Check in</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($b['reservation_status'] === 'active'): ?>
                                        <form method="post" style="display:inline;margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="checkout">
                                            <input type="hidden" name="reservation_id" value="<?= (int) $b['reservation_id'] ?>">
                                            <button class="btn btn--small" type="submit">Check out</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($b['reservation_status'], ['pending','confirmed'], true)): ?>
                                        <a class="btn btn--small btn--ghost"
                                           href="<?= e(url('cancel.php?reservation_id=' . (int) $b['reservation_id'])) ?>"
                                           data-confirm="Cancel this booking and refund the customer?">Cancel</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
