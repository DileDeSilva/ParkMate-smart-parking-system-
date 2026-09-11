<?php
/**
 * ParkMate - car park owner dashboard
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('owner', 'admin');
$ownerId = user_id();

$earnings = db()->prepare(
    'SELECT * FROM vw_owner_earnings WHERE owner_id = ? ORDER BY gross_revenue DESC'
);
$earnings->execute([$ownerId]);
$lots = $earnings->fetchAll();

$totals = db()->prepare(
    'SELECT
        COUNT(DISTINCT pl.lot_id)  AS lot_count,
        COUNT(ps.slot_id)          AS slot_count,
        COALESCE(SUM(ps.status = "maintenance"), 0) AS closed_count
       FROM parking_lots pl
  LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
      WHERE pl.owner_id = ?'
);
$totals->execute([$ownerId]);
$t = $totals->fetch();

$revenue = db()->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN p.status = "paid" THEN r.total_amount END), 0) AS gross,
        COALESCE(SUM(CASE WHEN p.status = "paid"
                           AND MONTH(r.start_time) = MONTH(CURDATE())
                           AND YEAR(r.start_time)  = YEAR(CURDATE())
                          THEN r.total_amount END), 0) AS this_month,
        COUNT(r.reservation_id) AS bookings,
        COALESCE(SUM(r.status IN ("pending","confirmed") AND r.end_time >= NOW()), 0) AS upcoming
       FROM parking_lots pl
       JOIN parking_slots ps ON ps.lot_id = pl.lot_id
  LEFT JOIN reservations  r  ON r.slot_id = ps.slot_id
  LEFT JOIN payments      p  ON p.reservation_id = r.reservation_id
      WHERE pl.owner_id = ?'
);
$revenue->execute([$ownerId]);
$rev = $revenue->fetch();

// Bays occupied at this moment, across all this owner's car parks.
$live = db()->prepare(
    'SELECT COUNT(*) FROM reservations r
       JOIN parking_slots ps ON ps.slot_id = r.slot_id
       JOIN parking_lots  pl ON pl.lot_id  = ps.lot_id
      WHERE pl.owner_id = ?
        AND r.status IN ("confirmed","active")
        AND NOW() BETWEEN r.start_time AND r.end_time'
);
$live->execute([$ownerId]);
$occupiedNow = (int) $live->fetchColumn();

$recent = db()->prepare(
    'SELECT vrd.* FROM vw_reservation_details vrd
      WHERE vrd.owner_id = ?
   ORDER BY vrd.created_at DESC LIMIT 8'
);
$recent->execute([$ownerId]);
$recentBookings = $recent->fetchAll();

$plan = db()->prepare(
    'SELECT sp.plan_name, sp.monthly_fee, sp.commission_rate, os.expires_on, os.status
       FROM owner_subscriptions os
       JOIN subscription_plans sp ON sp.plan_id = os.plan_id
      WHERE os.owner_id = ? AND os.status = "active"
   ORDER BY os.expires_on DESC LIMIT 1'
);
$plan->execute([$ownerId]);
$subscription = $plan->fetch();

$pageTitle = 'Owner dashboard';
$navKey    = 'o-dash';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Dashboard</h1>
                <p class="muted">How your car parks are doing today.</p>
            </div>
            <a class="btn btn--primary" href="<?= e(url('owner/lots.php')) ?>">Manage car parks</a>
        </div>

        <div class="grid grid--4" style="margin-bottom:30px">
            <div class="stat stat--money">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($rev['this_month'])) ?></span>
                <span class="stat__label">taken this month</span>
            </div>
            <div class="stat">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($rev['gross'])) ?></span>
                <span class="stat__label">taken all time</span>
            </div>
            <div class="stat stat--free">
                <span class="stat__value"><?= $occupiedNow ?> / <?= (int) $t['slot_count'] ?></span>
                <span class="stat__label">bays occupied right now</span>
            </div>
            <div class="stat <?= (int) $t['closed_count'] > 0 ? 'stat--alert' : '' ?>">
                <span class="stat__value"><?= (int) $t['closed_count'] ?></span>
                <span class="stat__label">bays out of service</span>
            </div>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <div class="card__head">
                    <h2>Your car parks</h2>
                    <a class="small" href="<?= e(url('owner/lots.php')) ?>">Edit details</a>
                </div>

                <?php if (!$lots): ?>
                    <div class="empty">
                        <h3>No car parks listed</h3>
                        <p>Add your first one and start taking bookings.</p>
                        <a class="btn btn--primary" href="<?= e(url('owner/lots.php')) ?>">Add a car park</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Car park</th>
                                    <th class="num">Bookings</th>
                                    <th class="num">Cancelled</th>
                                    <th class="num">Revenue</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lots as $lot): ?>
                                    <tr>
                                        <td><strong><?= e($lot['lot_name']) ?></strong></td>
                                        <td class="num"><?= (int) $lot['total_bookings'] ?></td>
                                        <td class="num"><?= (int) $lot['cancelled_count'] ?></td>
                                        <td class="num"><?= e(money($lot['gross_revenue'])) ?></td>
                                        <td class="nowrap">
                                            <a class="btn btn--small btn--ghost"
                                               href="<?= e(url('owner/slots.php?lot_id=' . (int) $lot['lot_id'])) ?>">
                                                Bays
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="card__head" style="margin-top:30px">
                    <h2>Latest bookings</h2>
                    <a class="small" href="<?= e(url('owner/reservations.php')) ?>">See all</a>
                </div>

                <?php if (!$recentBookings): ?>
                    <div class="empty"><p>No bookings have come in yet.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Bay</th>
                                    <th>Window</th>
                                    <th class="num">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td>
                                            <?= e($b['customer_name']) ?><br>
                                            <span class="muted small"><?= e($b['plate_number']) ?></span>
                                        </td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <aside>
                <div class="card">
                    <h3>Your plan</h3>
                    <?php if ($subscription): ?>
                        <p style="margin-bottom:6px">
                            <strong><?= e($subscription['plan_name']) ?></strong>
                        </p>
                        <p class="muted small" style="margin:0">
                            <?= e(money($subscription['monthly_fee'])) ?> a month &middot;
                            ParkMate keeps <?= e((string) $subscription['commission_rate']) ?>% of each booking.
                        </p>
                        <p class="muted small" style="margin:8px 0 0">
                            Renews <?= e(date('d M Y', strtotime($subscription['expires_on']))) ?>.
                        </p>
                    <?php else: ?>
                        <p class="muted small">
                            No active plan. Contact ParkMate to list your car parks.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-top:16px">
                    <h3>At a glance</h3>
                    <dl style="display:grid;grid-template-columns:1fr auto;gap:7px 12px;margin:0;font-size:.9rem">
                        <dt class="muted">Car parks</dt><dd style="margin:0;text-align:right"><?= (int) $t['lot_count'] ?></dd>
                        <dt class="muted">Bays</dt><dd style="margin:0;text-align:right"><?= (int) $t['slot_count'] ?></dd>
                        <dt class="muted">Bookings taken</dt><dd style="margin:0;text-align:right"><?= (int) $rev['bookings'] ?></dd>
                        <dt class="muted">Still upcoming</dt><dd style="margin:0;text-align:right"><?= (int) $rev['upcoming'] ?></dd>
                    </dl>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
