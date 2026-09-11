<?php
/**
 * ParkMate - a customer's vehicles
 */

require_once __DIR__ . '/config/config.php';

require_role('customer');
$uid = user_id();

$errors = [];
$plate  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ----- add a vehicle -----
    if ($action === 'add') {
        $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
        $type  = $_POST['vehicle_type'] ?? 'car';
        $model = trim($_POST['make_model'] ?? '');

        if (!in_array($type, ['car', 'suv', 'van', 'motorcycle'], true)) {
            $type = 'car';
        }
        // Sri Lankan plates: two or three letters, a dash, then four digits.
        if (!preg_match('/^[A-Z]{2,3}-\d{4}$/', $plate)) {
            $errors['plate_number'] = 'Use the format CAB-1234 or KY-3092.';
        } else {
            $dupe = db()->prepare('SELECT 1 FROM vehicles WHERE plate_number = ?');
            $dupe->execute([$plate]);
            if ($dupe->fetchColumn()) {
                $errors['plate_number'] = 'That plate is already registered on ParkMate.';
            }
        }

        if (!$errors) {
            $insert = db()->prepare(
                'INSERT INTO vehicles (user_id, plate_number, vehicle_type, make_model)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$uid, $plate, $type, $model !== '' ? $model : null]);
            flash('success', $plate . ' added.');
            redirect('vehicles.php');
        }
    }

    // ----- remove a vehicle -----
    if ($action === 'delete') {
        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);

        $inUse = db()->prepare(
            'SELECT COUNT(*) FROM reservations
              WHERE vehicle_id = ? AND status IN ("pending","confirmed","active")'
        );
        $inUse->execute([$vehicleId]);

        if ((int) $inUse->fetchColumn() > 0) {
            flash('error', 'That vehicle has a booking coming up. Cancel it first.');
        } else {
            $del = db()->prepare('DELETE FROM vehicles WHERE vehicle_id = ? AND user_id = ?');
            $del->execute([$vehicleId, $uid]);
            flash('success', 'Vehicle removed.');
        }
        redirect('vehicles.php');
    }
}

$list = db()->prepare(
    'SELECT v.*,
            (SELECT COUNT(*) FROM reservations r WHERE r.vehicle_id = v.vehicle_id) AS trips
       FROM vehicles v WHERE v.user_id = ? ORDER BY v.plate_number'
);
$list->execute([$uid]);
$vehicles = $list->fetchAll();

$pageTitle = 'My vehicles';
$navKey    = 'vehicles';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>My vehicles</h1>
                <p class="muted">Bookings are tied to a plate, so the car park knows what to expect.</p>
            </div>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <?php if (!$vehicles): ?>
                    <div class="empty">
                        <h3>No vehicles yet</h3>
                        <p>Add one and you can start booking bays.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Plate</th>
                                    <th>Type</th>
                                    <th>Make and model</th>
                                    <th class="num">Bookings</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v): ?>
                                    <tr>
                                        <td class="code"><?= e($v['plate_number']) ?></td>
                                        <td><?= e(ucfirst($v['vehicle_type'])) ?></td>
                                        <td><?= e($v['make_model'] ?? '-') ?></td>
                                        <td class="num"><?= (int) $v['trips'] ?></td>
                                        <td>
                                            <form method="post" style="margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="vehicle_id" value="<?= (int) $v['vehicle_id'] ?>">
                                                <button class="btn btn--small btn--ghost" type="submit"
                                                        data-confirm="Remove <?= e($v['plate_number']) ?>?">
                                                    Remove
                                                </button>
                                            </form>
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
                    <h3>Add a vehicle</h3>
                    <form method="post" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">

                        <label class="field">
                            <span>Number plate</span>
                            <input type="text" name="plate_number" placeholder="CAB-1234"
                                   value="<?= e($plate) ?>" required
                                   style="text-transform:uppercase">
                            <?php if (isset($errors['plate_number'])): ?>
                                <p class="form-error"><?= e($errors['plate_number']) ?></p>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Type</span>
                            <select name="vehicle_type">
                                <option value="car">Car</option>
                                <option value="suv">SUV</option>
                                <option value="van">Van</option>
                                <option value="motorcycle">Motorcycle</option>
                            </select>
                        </label>

                        <label class="field">
                            <span>Make and model <span class="muted">(optional)</span></span>
                            <input type="text" name="make_model" placeholder="Toyota Aqua">
                        </label>

                        <button class="btn btn--primary btn--block" type="submit">Add vehicle</button>
                    </form>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
