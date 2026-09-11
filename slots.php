<?php
/**
 * ParkMate - manage the bays inside one car park (owner)
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('owner', 'admin');

$lotId = (int) ($_GET['lot_id'] ?? $_POST['lot_id'] ?? 0);

if (!owns_lot($lotId)) {
    http_response_code(403);
    flash('error', 'That car park is not yours to manage.');
    redirect('owner/lots.php');
}

$stmt = db()->prepare(
    'SELECT pl.*, c.city_name FROM parking_lots pl
       JOIN cities c ON c.city_id = pl.city_id
      WHERE pl.lot_id = ?'
);
$stmt->execute([$lotId]);
$lot = $stmt->fetch();

if (!$lot) {
    flash('error', 'That car park was not found.');
    redirect('owner/lots.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ----- add one bay, or a run of them -----
    if ($action === 'add') {
        $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
        $from   = (int) ($_POST['from'] ?? 1);
        $to     = (int) ($_POST['to'] ?? 1);
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $floor  = trim($_POST['floor_level'] ?? 'Ground');
        $rate   = (float) ($_POST['hourly_rate'] ?? 0);

        if (!preg_match('/^[A-Z]{1,3}$/', $prefix)) {
            $errors['prefix'] = 'Use 1 to 3 letters, like A or LG.';
        }
        if ($from < 1 || $to < $from || ($to - $from) > 99) {
            $errors['from'] = 'Give a range of 1 to 100 bays, counting upward.';
        }
        if ($rate <= 0) {
            $errors['hourly_rate'] = 'Set an hourly rate above zero.';
        }
        if ($floor === '') {
            $floor = 'Ground';
        }

        if (!$errors) {
            $insert = db()->prepare(
                'INSERT IGNORE INTO parking_slots
                    (lot_id, type_id, slot_code, floor_level, hourly_rate)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $added = 0;
            for ($i = $from; $i <= $to; $i++) {
                $code = $prefix . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $insert->execute([$lotId, $typeId, $code, $floor, $rate]);
                $added += $insert->rowCount();
            }

            $skipped = ($to - $from + 1) - $added;
            $msg = $added . ' ' . ($added === 1 ? 'bay' : 'bays') . ' added.';
            if ($skipped > 0) {
                $msg .= ' ' . $skipped . ' skipped because those codes already exist.';
            }
            flash('success', $msg);
            redirect('owner/slots.php?lot_id=' . $lotId);
        }
    }

    // ----- open or close a bay -----
    if ($action === 'toggle') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        db()->prepare(
            'UPDATE parking_slots
                SET status = IF(status = "available", "maintenance", "available")
              WHERE slot_id = ? AND lot_id = ?'
        )->execute([$slotId, $lotId]);
        flash('success', 'Bay updated.');
        redirect('owner/slots.php?lot_id=' . $lotId);
    }

    // ----- change a rate -----
    if ($action === 'reprice') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $rate   = (float) ($_POST['hourly_rate'] ?? 0);

        if ($rate <= 0) {
            flash('error', 'The hourly rate has to be above zero.');
        } else {
            db()->prepare(
                'UPDATE parking_slots SET hourly_rate = ? WHERE slot_id = ? AND lot_id = ?'
            )->execute([$rate, $slotId, $lotId]);
            flash('success', 'Rate updated. Bookings already made keep their old price.');
        }
        redirect('owner/slots.php?lot_id=' . $lotId);
    }

    // ----- remove a bay -----
    if ($action === 'delete') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);

        $inUse = db()->prepare(
            'SELECT COUNT(*) FROM reservations
              WHERE slot_id = ? AND status IN ("pending","confirmed","active")'
        );
        $inUse->execute([$slotId]);

        if ((int) $inUse->fetchColumn() > 0) {
            flash('error', 'That bay has bookings on it. Close it for maintenance instead.');
        } else {
            db()->prepare('DELETE FROM parking_slots WHERE slot_id = ? AND lot_id = ?')
                ->execute([$slotId, $lotId]);
            flash('success', 'Bay removed.');
        }
        redirect('owner/slots.php?lot_id=' . $lotId);
    }
}

$types = db()->query('SELECT type_id, type_name FROM slot_types ORDER BY type_id')->fetchAll();

// Every bay with whoever is parked in it right now.
$stmt = db()->prepare(
    'SELECT ps.*, st.type_name,
            (SELECT COUNT(*) FROM reservations r
              WHERE r.slot_id = ps.slot_id
                AND r.status IN ("pending","confirmed","active")
                AND r.end_time >= NOW())                       AS upcoming,
            (SELECT CONCAT(u.full_name, " (", v.plate_number, ")")
               FROM reservations r
               JOIN users u    ON u.user_id = r.user_id
               JOIN vehicles v ON v.vehicle_id = r.vehicle_id
              WHERE r.slot_id = ps.slot_id
                AND r.status IN ("confirmed","active")
                AND NOW() BETWEEN r.start_time AND r.end_time
              LIMIT 1)                                          AS occupant
       FROM parking_slots ps
       JOIN slot_types st ON st.type_id = ps.type_id
      WHERE ps.lot_id = ?
   ORDER BY ps.floor_level, ps.slot_code'
);
$stmt->execute([$lotId]);
$slots = $stmt->fetchAll();

$pageTitle = 'Bays at ' . $lot['lot_name'];
$navKey    = 'o-lots';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1><?= e($lot['lot_name']) ?></h1>
                <p class="muted">
                    <?= e($lot['address']) ?> &middot; <?= count($slots) ?> bays
                </p>
            </div>
            <a class="btn btn--ghost" href="<?= e(url('owner/lots.php')) ?>">All car parks</a>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <?php if (!$slots): ?>
                    <div class="empty">
                        <h3>No bays yet</h3>
                        <p>Add a run of bays using the form beside this.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bay</th>
                                    <th>Floor</th>
                                    <th>Type</th>
                                    <th>Rate per hour</th>
                                    <th>State</th>
                                    <th class="num">Booked</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td class="code"><?= e($slot['slot_code']) ?></td>
                                        <td class="small"><?= e($slot['floor_level']) ?></td>
                                        <td class="small"><?= e($slot['type_name']) ?></td>
                                        <td>
                                            <form method="post" class="row" style="gap:6px;margin:0;flex-wrap:nowrap">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reprice">
                                                <input type="hidden" name="lot_id" value="<?= $lotId ?>">
                                                <input type="hidden" name="slot_id" value="<?= (int) $slot['slot_id'] ?>">
                                                <input type="number" name="hourly_rate" step="5" min="0"
                                                       value="<?= e((string) (float) $slot['hourly_rate']) ?>"
                                                       style="width:92px;padding:5px 7px">
                                                <button class="btn btn--small btn--ghost" type="submit">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="<?= e(status_class($slot['status'])) ?>">
                                                <?= $slot['status'] === 'available' ? 'Open' : 'Closed' ?>
                                            </span>
                                            <?php if ($slot['occupant']): ?>
                                                <br><span class="muted small"><?= e($slot['occupant']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="num"><?= (int) $slot['upcoming'] ?></td>
                                        <td class="nowrap">
                                            <form method="post" style="display:inline;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="lot_id" value="<?= $lotId ?>">
                                                <input type="hidden" name="slot_id" value="<?= (int) $slot['slot_id'] ?>">
                                                <button class="btn btn--small btn--ghost" type="submit">
                                                    <?= $slot['status'] === 'available' ? 'Close' : 'Open' ?>
                                                </button>
                                            </form>
                                            <form method="post" style="display:inline;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="lot_id" value="<?= $lotId ?>">
                                                <input type="hidden" name="slot_id" value="<?= (int) $slot['slot_id'] ?>">
                                                <button class="btn btn--small btn--ghost" type="submit"
                                                        data-confirm="Remove bay <?= e($slot['slot_code']) ?>?">
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
                    <h3>Add bays</h3>
                    <p class="muted small">
                        Bays are numbered for you. A prefix of A from 1 to 6 creates
                        A-01 through A-06.
                    </p>

                    <form method="post" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="lot_id" value="<?= $lotId ?>">

                        <label class="field">
                            <span>Bay prefix</span>
                            <input type="text" name="prefix" value="A" maxlength="3" required
                                   style="text-transform:uppercase">
                            <?php if (isset($errors['prefix'])): ?>
                                <p class="form-error"><?= e($errors['prefix']) ?></p>
                            <?php endif; ?>
                        </label>

                        <div class="grid grid--2">
                            <label class="field">
                                <span>From</span>
                                <input type="number" name="from" value="1" min="1" max="999" required>
                            </label>
                            <label class="field">
                                <span>To</span>
                                <input type="number" name="to" value="6" min="1" max="999" required>
                            </label>
                        </div>
                        <?php if (isset($errors['from'])): ?>
                            <p class="form-error"><?= e($errors['from']) ?></p>
                        <?php endif; ?>

                        <label class="field">
                            <span>Floor or level</span>
                            <input type="text" name="floor_level" value="Ground" required>
                        </label>

                        <label class="field">
                            <span>Bay type</span>
                            <select name="type_id" required>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= (int) $type['type_id'] ?>">
                                        <?= e($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            <span>Rate per hour (<?= e(CURRENCY) ?>)</span>
                            <input type="number" name="hourly_rate" value="100" step="5" min="1" required>
                            <?php if (isset($errors['hourly_rate'])): ?>
                                <p class="form-error"><?= e($errors['hourly_rate']) ?></p>
                            <?php endif; ?>
                        </label>

                        <button class="btn btn--primary btn--block" type="submit">Add these bays</button>
                    </form>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
