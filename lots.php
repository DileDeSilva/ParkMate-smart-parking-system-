<?php
/**
 * ParkMate - manage car parks (owner)
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('owner', 'admin');
$ownerId = user_id();

$errors  = [];
$editing = null;
$form = [
    'lot_name' => '', 'address' => '', 'city_id' => '', 'description' => '',
    'opening_time' => '06:00', 'closing_time' => '22:00',
];

// Load a car park into the form for editing.
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if (owns_lot($editId)) {
        $stmt = db()->prepare('SELECT * FROM parking_lots WHERE lot_id = ?');
        $stmt->execute([$editId]);
        $editing = $stmt->fetch();
        if ($editing) {
            $form = [
                'lot_name'     => $editing['lot_name'],
                'address'      => $editing['address'],
                'city_id'      => (string) $editing['city_id'],
                'description'  => (string) $editing['description'],
                'opening_time' => substr($editing['opening_time'], 0, 5),
                'closing_time' => substr($editing['closing_time'], 0, 5),
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ----- toggle active / inactive -----
    if ($action === 'toggle') {
        $lotId = (int) ($_POST['lot_id'] ?? 0);
        if (owns_lot($lotId)) {
            db()->prepare(
                'UPDATE parking_lots
                    SET status = IF(status = "active", "inactive", "active")
                  WHERE lot_id = ?'
            )->execute([$lotId]);
            flash('success', 'Listing updated.');
        }
        redirect('owner/lots.php');
    }

    // ----- create or update -----
    if ($action === 'save') {
        $lotId = (int) ($_POST['lot_id'] ?? 0);

        $form['lot_name']     = trim($_POST['lot_name'] ?? '');
        $form['address']      = trim($_POST['address'] ?? '');
        $form['city_id']      = trim($_POST['city_id'] ?? '');
        $form['description']  = trim($_POST['description'] ?? '');
        $form['opening_time'] = $_POST['opening_time'] ?? '06:00';
        $form['closing_time'] = $_POST['closing_time'] ?? '22:00';

        if (mb_strlen($form['lot_name']) < 4) {
            $errors['lot_name'] = 'Give the car park a name of at least 4 characters.';
        }
        if (mb_strlen($form['address']) < 8) {
            $errors['address'] = 'Write out the full street address.';
        }
        if ($form['city_id'] === '') {
            $errors['city_id'] = 'Choose the area it is in.';
        }
        if ($form['closing_time'] <= $form['opening_time']) {
            $errors['closing_time'] = 'Closing time has to be later than opening time.';
        }

        if (!$errors) {
            if ($lotId > 0 && owns_lot($lotId)) {
                db()->prepare(
                    'UPDATE parking_lots
                        SET lot_name = ?, address = ?, city_id = ?, description = ?,
                            opening_time = ?, closing_time = ?
                      WHERE lot_id = ?'
                )->execute([
                    $form['lot_name'], $form['address'], (int) $form['city_id'],
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['opening_time'], $form['closing_time'], $lotId,
                ]);
                flash('success', $form['lot_name'] . ' updated.');
            } else {
                db()->prepare(
                    'INSERT INTO parking_lots
                        (owner_id, city_id, lot_name, address, description,
                         opening_time, closing_time)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $ownerId, (int) $form['city_id'], $form['lot_name'], $form['address'],
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['opening_time'], $form['closing_time'],
                ]);
                flash('success', $form['lot_name'] . ' added. Now set up its bays.');
                redirect('owner/slots.php?lot_id=' . (int) db()->lastInsertId());
            }
            redirect('owner/lots.php');
        }
    }
}

$cities = db()->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();

$stmt = db()->prepare(
    'SELECT pl.*, c.city_name,
            COUNT(ps.slot_id) AS slot_count
       FROM parking_lots pl
       JOIN cities c ON c.city_id = pl.city_id
  LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
      WHERE pl.owner_id = ?
   GROUP BY pl.lot_id, c.city_name
   ORDER BY pl.lot_name'
);
$stmt->execute([$ownerId]);
$lots = $stmt->fetchAll();

$pageTitle = 'My car parks';
$navKey    = 'o-lots';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>My car parks</h1>
                <p class="muted">Names, addresses and opening hours as drivers see them.</p>
            </div>
        </div>

        <div class="grid grid--sidebar">
            <section>
                <?php if (!$lots): ?>
                    <div class="empty">
                        <h3>Nothing listed yet</h3>
                        <p>Fill in the form to add your first car park.</p>
                    </div>
                <?php else: ?>
                    <div class="stack">
                        <?php foreach ($lots as $lot): ?>
                            <article class="lot <?= $lot['status'] === 'active' ? '' : 'lot--full' ?>">
                                <div class="lot__main">
                                    <h3 class="lot__name"><?= e($lot['lot_name']) ?></h3>
                                    <p class="lot__meta"><?= e($lot['address']) ?></p>
                                    <p class="lot__meta">
                                        <?= e($lot['city_name']) ?> &middot;
                                        <?= e(tm($lot['opening_time'])) ?>&ndash;<?= e(tm($lot['closing_time'])) ?>
                                        &middot; <?= (int) $lot['slot_count'] ?> bays
                                        <span class="<?= e(status_class($lot['status'])) ?>">
                                            <?= e(ucfirst($lot['status'])) ?>
                                        </span>
                                    </p>
                                </div>

                                <div class="row">
                                    <a class="btn btn--small btn--ghost"
                                       href="<?= e(url('owner/slots.php?lot_id=' . (int) $lot['lot_id'])) ?>">Bays</a>
                                    <a class="btn btn--small btn--ghost"
                                       href="<?= e(url('owner/lots.php?edit=' . (int) $lot['lot_id'])) ?>">Edit</a>
                                    <form method="post" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="lot_id" value="<?= (int) $lot['lot_id'] ?>">
                                        <button class="btn btn--small btn--ghost" type="submit">
                                            <?= $lot['status'] === 'active' ? 'Take offline' : 'Put online' ?>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside>
                <div class="card">
                    <h3><?= $editing ? 'Edit car park' : 'Add a car park' ?></h3>

                    <form method="post" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <?php if ($editing): ?>
                            <input type="hidden" name="lot_id" value="<?= (int) $editing['lot_id'] ?>">
                        <?php endif; ?>

                        <label class="field">
                            <span>Name</span>
                            <input type="text" name="lot_name" value="<?= e($form['lot_name']) ?>" required>
                            <?php if (isset($errors['lot_name'])): ?>
                                <p class="form-error"><?= e($errors['lot_name']) ?></p>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Street address</span>
                            <input type="text" name="address" value="<?= e($form['address']) ?>" required>
                            <?php if (isset($errors['address'])): ?>
                                <p class="form-error"><?= e($errors['address']) ?></p>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Area</span>
                            <select name="city_id" required>
                                <option value="">Choose one</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= (int) $city['city_id'] ?>"
                                        <?= $form['city_id'] === (string) $city['city_id'] ? 'selected' : '' ?>>
                                        <?= e($city['city_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['city_id'])): ?>
                                <p class="form-error"><?= e($errors['city_id']) ?></p>
                            <?php endif; ?>
                        </label>

                        <div class="grid grid--2">
                            <label class="field">
                                <span>Opens</span>
                                <input type="time" name="opening_time" value="<?= e($form['opening_time']) ?>" required>
                            </label>
                            <label class="field">
                                <span>Closes</span>
                                <input type="time" name="closing_time" value="<?= e($form['closing_time']) ?>" required>
                                <?php if (isset($errors['closing_time'])): ?>
                                    <p class="form-error"><?= e($errors['closing_time']) ?></p>
                                <?php endif; ?>
                            </label>
                        </div>

                        <label class="field">
                            <span>What should drivers know? <span class="muted">(optional)</span></span>
                            <textarea name="description" maxlength="600"><?= e($form['description']) ?></textarea>
                        </label>

                        <button class="btn btn--primary btn--block" type="submit">
                            <?= $editing ? 'Save changes' : 'Add car park' ?>
                        </button>

                        <?php if ($editing): ?>
                            <a class="btn btn--ghost btn--block" href="<?= e(url('owner/lots.php')) ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </aside>
        </div>

    </div>
</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
