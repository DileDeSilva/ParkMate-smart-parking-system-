<?php
/**
 * ParkMate - administrator dashboard
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('admin');

$counts = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM users WHERE role = "customer")            AS customers,
        (SELECT COUNT(*) FROM users WHERE role = "owner")               AS owners,
        (SELECT COUNT(*) FROM users WHERE status = "suspended")         AS suspended,
        (SELECT COUNT(*) FROM parking_lots)                             AS lots,
        (SELECT COUNT(*) FROM parking_lots WHERE status = "active")     AS active_lots,
        (SELECT COUNT(*) FROM parking_slots)                            AS slots,
        (SELECT COUNT(*) FROM reservations)                             AS bookings,
        (SELECT COUNT(*) FROM reservations WHERE status = "cancelled")  AS cancelled,
        (SELECT COUNT(*) FROM vehicles)                                 AS vehicles'
)->fetch();

$money = db()->query(
    'SELECT
        COALESCE(SUM(CASE WHEN status = "paid" THEN amount END), 0)     AS collected,
        COALESCE(SUM(CASE WHEN status = "pending" THEN amount END), 0)  AS outstanding,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN amount END), 0) AS refunded
       FROM payments'
)->fetch();

// Commission ParkMate earns, using each owner's plan rate.
$commission = db()->query(
    'SELECT COALESCE(SUM(r.total_amount * sp.commission_rate / 100), 0) AS earned
       FROM reservations r
       JOIN payments      p  ON p.reservation_id = r.reservation_id AND p.status = "paid"
       JOIN parking_slots ps ON ps.slot_id = r.slot_id
       JOIN parking_lots  pl ON pl.lot_id  = ps.lot_id
       JOIN owner_subscriptions os ON os.owner_id = pl.owner_id AND os.status = "active"
       JOIN subscription_plans  sp ON sp.plan_id  = os.plan_id'
)->fetchColumn();

// Bookings per day over the last week, for the trend table.
$trend = db()->query(
    'SELECT DATE(created_at) AS day,
            COUNT(*)         AS bookings,
            COALESCE(SUM(total_amount), 0) AS value
       FROM reservations
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
   GROUP BY DATE(created_at)
   ORDER BY day DESC'
)->fetchAll();

$busiest = db()->query(
    'SELECT * FROM vw_lot_availability
      WHERE lot_status = "active"
   ORDER BY (total_slots - free_slots) DESC, lot_name
      LIMIT 5'
)->fetchAll();

$newest = db()->query(
    'SELECT user_id, full_name, email, role, created_at
       FROM users ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

$pageTitle = 'Admin dashboard';
$navKey    = 'a-dash';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Platform overview</h1>
                <p class="muted">Everything happening across ParkMate.</p>
            </div>
        </div>

        <div class="grid grid--4" style="margin-bottom:18px">
            <div class="stat stat--money">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($money['collected'])) ?></span>
                <span class="stat__label">collected from drivers</span>
            </div>
            <div class="stat stat--money">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($commission)) ?></span>
                <span class="stat__label">ParkMate commission earned</span>
            </div>
            <div class="stat">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($money['outstanding'])) ?></span>
                <span class="stat__label">held, awaiting payment</span>
            </div>
            <div class="stat stat--alert">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($money['refunded'])) ?></span>
                <span class="stat__label">refunded on cancellations</span>
            </div>
        </div>

        <div class="grid grid--4" style="margin-bottom:30px">
            <div class="stat stat--free">
                <span class="stat__value"><?= (int) $counts['customers'] ?></span>
                <span class="stat__label">drivers registered</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $counts['owners'] ?></span>
                <span class="stat__label">car park operators</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $counts['active_lots'] ?> / <?= (int) $counts['lots'] ?></span>
                <span class="stat__label">car parks listed</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $counts['slots'] ?></span>
                <span class="stat__label">bays on the platform</span>
            </div>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <div class="card__head">
                    <h2>Bookings this week</h2>
                    <span class="muted small"><?= (int) $counts['bookings'] ?> all time</span>
                </div>

                <?php if (!$trend): ?>
                    <div class="empty"><p>No bookings made in the last seven days.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th class="num">Bookings</th>
                                    <th class="num">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trend as $day): ?>
                                    <tr>
                                        <td><?= e(date('D, d M Y', strtotime($day['day']))) ?></td>
                                        <td class="num"><?= (int) $day['bookings'] ?></td>
                                        <td class="num"><?= e(money($day['value'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="card__head" style="margin-top:30px">
                    <h2>Busiest car parks</h2>
                    <a class="small" href="<?= e(url('admin/lots.php')) ?>">All car parks</a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Car park</th>
                                <th>Operator</th>
                                <th class="num">Bays</th>
                                <th class="num">Free now</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($busiest as $lot): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($lot['lot_name']) ?></strong><br>
                                        <span class="muted small"><?= e($lot['city_name']) ?></span>
                                    </td>
                                    <td class="small"><?= e($lot['owner_name']) ?></td>
                                    <td class="num"><?= (int) $lot['total_slots'] ?></td>
                                    <td class="num"><?= (int) $lot['free_slots'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside>
                <div class="card">
                    <h3>Newest accounts</h3>
                    <?php foreach ($newest as $person): ?>
                        <div class="note">
                            <h4><?= e($person['full_name']) ?></h4>
                            <p><?= e($person['email']) ?></p>
                            <p class="small" style="color:#8b939c">
                                <?= e(ucfirst($person['role'])) ?> &middot;
                                joined <?= e(date('d M Y', strtotime($person['created_at']))) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                    <a class="btn btn--ghost btn--block" style="margin-top:12px"
                       href="<?= e(url('admin/users.php')) ?>">Manage users</a>
                </div>

                <?php if ((int) $counts['suspended'] > 0): ?>
                    <div class="notice notice--error" style="margin-top:16px">
                        <?= (int) $counts['suspended'] ?>
                        <?= (int) $counts['suspended'] === 1 ? 'account is' : 'accounts are' ?>
                        suspended.
                        <a href="<?= e(url('admin/users.php?role=all&status=suspended')) ?>">Review them</a>.
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-top:16px">
                    <h3>Other numbers</h3>
                    <dl style="display:grid;grid-template-columns:1fr auto;gap:7px 12px;margin:0;font-size:.9rem">
                        <dt class="muted">Vehicles registered</dt>
                        <dd style="margin:0;text-align:right"><?= (int) $counts['vehicles'] ?></dd>
                        <dt class="muted">Bookings cancelled</dt>
                        <dd style="margin:0;text-align:right"><?= (int) $counts['cancelled'] ?></dd>
                    </dl>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
