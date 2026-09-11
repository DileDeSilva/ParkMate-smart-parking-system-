<?php
/**
 * ParkMate - customer dashboard
 */

require_once __DIR__ . '/config/config.php';

require_role('customer');
$uid = user_id();

// Anything that has run past its end time is finished.
$sweep = db()->prepare(
    'UPDATE reservations
        SET status = "completed"
      WHERE user_id = ? AND status IN ("confirmed","active") AND end_time < NOW()'
);
$sweep->execute([$uid]);

$upcoming = db()->prepare(
    'SELECT * FROM vw_reservation_details
      WHERE user_id = ?
        AND reservation_status IN ("pending","confirmed","active")
        AND end_time >= NOW()
   ORDER BY start_time
      LIMIT 5'
);
$upcoming->execute([$uid]);
$bookings = $upcoming->fetchAll();

$stats = db()->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "completed") AS completed,
        SUM(status IN ("pending","confirmed","active")) AS active,
        COALESCE(SUM(CASE WHEN status <> "cancelled" THEN total_amount ELSE 0 END), 0) AS spent
       FROM reservations WHERE user_id = ?'
);
$stats->execute([$uid]);
$s = $stats->fetch();

$notes = db()->prepare(
    'SELECT * FROM notifications WHERE user_id = ?
   ORDER BY created_at DESC LIMIT 8'
);
$notes->execute([$uid]);
$notifications = $notes->fetchAll();

// Opening the dashboard is what marks them read.
$mark = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$mark->execute([$uid]);

$pageTitle = 'My parking';
$navKey    = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Hello, <?= e(current_user()['full_name']) ?></h1>
                <p class="muted">Here is where your parking stands.</p>
            </div>
            <a class="btn btn--primary" href="<?= e(url('search.php')) ?>">Book a bay</a>
        </div>

        <div class="grid grid--4" style="margin-bottom:30px">
            <div class="stat stat--free">
                <span class="stat__value"><?= (int) $s['active'] ?></span>
                <span class="stat__label">bookings coming up</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $s['completed'] ?></span>
                <span class="stat__label">stays completed</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?= (int) $s['total'] ?></span>
                <span class="stat__label">bookings all time</span>
            </div>
            <div class="stat stat--money">
                <span class="stat__value" style="font-size:1.5rem"><?= e(money($s['spent'])) ?></span>
                <span class="stat__label">spent on parking</span>
            </div>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <div class="card__head">
                    <h2>Coming up</h2>
                    <a class="small" href="<?= e(url('my-bookings.php')) ?>">All bookings</a>
                </div>

                <?php if (!$bookings): ?>
                    <div class="empty">
                        <h3>Nothing booked yet</h3>
                        <p>Find a car park near where you are headed and hold a bay.</p>
                        <a class="btn btn--primary" href="<?= e(url('search.php')) ?>">Find parking</a>
                    </div>
                <?php else: ?>
                    <div class="stack">
                        <?php foreach ($bookings as $b): ?>
                            <article class="lot <?= $b['reservation_status'] === 'pending' ? 'lot--tight' : '' ?>">
                                <div class="lot__main">
                                    <h3 class="lot__name"><?= e($b['lot_name']) ?></h3>
                                    <p class="lot__meta">
                                        Bay <?= e($b['slot_code']) ?> &middot; <?= e($b['floor_level']) ?>
                                        &middot; <?= e($b['plate_number']) ?>
                                    </p>
                                    <p class="lot__meta">
                                        <?= e(dt($b['start_time'])) ?> to <?= e(dt($b['end_time'])) ?>
                                    </p>
                                    <p style="margin:6px 0 0">
                                        <span class="<?= e(status_class($b['reservation_status'])) ?>">
                                            <?= e(ucfirst($b['reservation_status'])) ?>
                                        </span>
                                        <span class="<?= e(status_class((string) $b['payment_status'])) ?>">
                                            Payment <?= e((string) $b['payment_status']) ?>
                                        </span>
                                    </p>
                                </div>

                                <div class="lot__count" style="font-size:1.4rem">
                                    <?= e(money($b['total_amount'])) ?>
                                </div>

                                <?php if ($b['payment_status'] === 'pending'): ?>
                                    <a class="btn btn--primary"
                                       href="<?= e(url('payment.php?reservation_id=' . (int) $b['reservation_id'])) ?>">
                                        Pay now
                                    </a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside>
                <div class="card">
                    <h3>Recent activity</h3>
                    <?php if (!$notifications): ?>
                        <p class="muted small">Nothing to report yet.</p>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="note <?= (int) $n['is_read'] === 0 ? 'note--unread' : '' ?>">
                                <h4><?= e($n['title']) ?></h4>
                                <p><?= e($n['message']) ?></p>
                                <p class="small" style="color:#8b939c"><?= e(dt($n['created_at'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-top:16px">
                    <h3>Your vehicles</h3>
                    <p class="muted small">
                        Every booking is tied to a plate, so the car park knows what to expect.
                    </p>
                    <a class="btn btn--ghost btn--block" href="<?= e(url('vehicles.php')) ?>">
                        Manage vehicles
                    </a>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
