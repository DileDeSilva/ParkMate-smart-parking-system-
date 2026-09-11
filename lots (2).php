<?php
/**
 * ParkMate - car park oversight (administrator)
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $lotId  = (int) ($_POST['lot_id'] ?? 0);

    $stmt = db()->prepare('SELECT lot_name, status FROM parking_lots WHERE lot_id = ?');
    $stmt->execute([$lotId]);
    $lot = $stmt->fetch();

    if (!$lot) {
        flash('error', 'That car park was not found.');
        redirect('admin/lots.php');
    }

    if ($action === 'toggle') {
        db()->prepare(
            'UPDATE parking_lots
                SET status = IF(status = "active", "inactive", "active")
              WHERE lot_id = ?'
        )->execute([$lotId]);

        $now = $lot['status'] === 'active' ? 'taken offline' : 'put back online';
        flash('success', $lot['lot_name'] . ' has been ' . $now . '.');
    }

    redirect('admin/lots.php');
}

$cityId = isset($_GET['city_id']) && $_GET['city_id'] !== '' ? (int) $_GET['city_id'] : null;

$cities = db()->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();

$sql = 'SELECT pl.lot_id, pl.lot_name, pl.address, pl.status, pl.created_at,
               c.city_name,
               u.user_id AS owner_id, u.full_name AS owner_name, u.email AS owner_email,
               COUNT(DISTINCT ps.slot_id) AS slot_count,
               COUNT(DISTINCT r.reservation_id) AS booking_count,
               COALESCE(SUM(CASE WHEN p.status = "paid" THEN r.total_amount END), 0) AS revenue,
               ROUND(AVG(rv.rating), 1) AS avg_rating
          FROM parking_lots pl
          JOIN cities c ON c.city_id = pl.city_id
          JOIN users  u ON u.user_id = pl.owner_id
     LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
     LEFT JOIN reservations  r  ON r.slot_id = ps.slot_id
     LEFT JOIN payments      p  ON p.reservation_id = r.reservation_id
     LEFT JOIN reviews       rv ON rv.lot_id = pl.lot_id
         WHERE 1 = 1';
$params = [];

if ($cityId !== null) {
    $sql .= ' AND pl.city_id = :city_id';
    $params['city_id'] = $cityId;
}
$sql .= ' GROUP BY pl.lot_id, pl.lot_name, pl.address, pl.status, pl.created_at,
                   c.city_name, u.user_id, u.full_name, u.email
          ORDER BY pl.lot_name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$lots = $stmt->fetchAll();

$pageTitle = 'Car parks';
$navKey    = 'a-lots';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Car parks</h1>
                <p class="muted">Every listing on the platform and who operates it.</p>
            </div>
        </div>

        <form class="finder finder--inline" method="get" action="<?= e(url('admin/lots.php')) ?>"
              style="grid-template-columns:1fr auto">
            <label class="field">
                <span>Area</span>
                <select name="city_id">
                    <option value="">Everywhere</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= (int) $city['city_id'] ?>"
                            <?= $cityId === (int) $city['city_id'] ? 'selected' : '' ?>>
                            <?= e($city['city_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn--primary" type="submit">Apply</button>
        </form>

        <?php if (!$lots): ?>
            <div class="empty">
                <h3>No car parks listed</h3>
                <p>Nothing matches that filter.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Car park</th>
                            <th>Operator</th>
                            <th class="num">Bays</th>
                            <th class="num">Bookings</th>
                            <th class="num">Revenue</th>
                            <th>Rating</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lots as $lot): ?>
                            <tr>
                                <td>
                                    <strong><?= e($lot['lot_name']) ?></strong><br>
                                    <span class="muted small">
                                        <?= e($lot['city_name']) ?> &middot; <?= e($lot['address']) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?= e($lot['owner_name']) ?><br>
                                    <span class="muted"><?= e($lot['owner_email']) ?></span>
                                </td>
                                <td class="num"><?= (int) $lot['slot_count'] ?></td>
                                <td class="num"><?= (int) $lot['booking_count'] ?></td>
                                <td class="num"><?= e(money($lot['revenue'])) ?></td>
                                <td>
                                    <?php if ($lot['avg_rating'] !== null): ?>
                                        <span class="stars" aria-label="<?= e((string) $lot['avg_rating']) ?> out of 5">
                                            <?= str_repeat('&#9733;', (int) round((float) $lot['avg_rating'])) ?>
                                        </span><br>
                                        <span class="muted small"><?= e((string) $lot['avg_rating']) ?> / 5</span>
                                    <?php else: ?>
                                        <span class="muted small">No reviews</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= e(status_class($lot['status'])) ?>">
                                        <?= e(ucfirst($lot['status'])) ?>
                                    </span>
                                </td>
                                <td class="nowrap">
                                    <a class="btn btn--small btn--ghost"
                                       href="<?= e(url('lot.php?id=' . (int) $lot['lot_id'])) ?>">View</a>

                                    <form method="post" style="display:inline;margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="lot_id" value="<?= (int) $lot['lot_id'] ?>">
                                        <button class="btn btn--small btn--ghost" type="submit"
                                            data-confirm="<?= $lot['status'] === 'active'
                                                ? 'Take this car park offline? Drivers will stop seeing it.'
                                                : 'Put this car park back online?' ?>">
                                            <?= $lot['status'] === 'active' ? 'Take offline' : 'Put online' ?>
                                        </button>
                                    </form>
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
