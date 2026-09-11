<?php
/**
 * ParkMate - user management (administrator)
 */

require_once dirname(__DIR__) . '/config/config.php';

require_role('admin');
$adminId = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action     = $_POST['action'] ?? '';
    $targetId   = (int) ($_POST['user_id'] ?? 0);

    // An administrator must not be able to lock themselves out.
    if ($targetId === $adminId) {
        flash('error', 'You cannot change your own account from here.');
        redirect('admin/users.php');
    }

    $stmt = db()->prepare('SELECT full_name, role, status FROM users WHERE user_id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('error', 'That account was not found.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle_status') {
        db()->prepare(
            'UPDATE users
                SET status = IF(status = "active", "suspended", "active")
              WHERE user_id = ?'
        )->execute([$targetId]);

        $now = $target['status'] === 'active' ? 'suspended' : 'restored';
        flash('success', $target['full_name'] . ' has been ' . $now . '.');
    }

    if ($action === 'make_owner' && $target['role'] === 'customer') {
        // Only safe while the account has no bookings of its own.
        $held = db()->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ?');
        $held->execute([$targetId]);

        if ((int) $held->fetchColumn() > 0) {
            flash('error', 'That account has bookings, so it cannot become an operator account.');
        } else {
            db()->prepare('UPDATE users SET role = "owner" WHERE user_id = ?')->execute([$targetId]);
            flash('success', $target['full_name'] . ' is now a car park operator.');
        }
    }

    redirect('admin/users.php');
}

// ---------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------
$role   = $_GET['role']   ?? 'all';
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

if (!in_array($role, ['all', 'customer', 'owner', 'admin'], true)) {
    $role = 'all';
}
if (!in_array($status, ['all', 'active', 'suspended'], true)) {
    $status = 'all';
}

$sql = 'SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at,
               (SELECT COUNT(*) FROM reservations r  WHERE r.user_id  = u.user_id) AS bookings,
               (SELECT COUNT(*) FROM parking_lots pl WHERE pl.owner_id = u.user_id) AS lots
          FROM users u
         WHERE 1 = 1';
$params = [];

if ($role !== 'all') {
    $sql .= ' AND u.role = :role';
    $params['role'] = $role;
}
if ($status !== 'all') {
    $sql .= ' AND u.status = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE :term_a OR u.email LIKE :term_b)';
    $params['term_a'] = '%' . $search . '%';
    $params['term_b'] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.created_at DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
$navKey    = 'a-users';
require dirname(__DIR__) . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Users</h1>
                <p class="muted">Everyone with a ParkMate account.</p>
            </div>
        </div>

        <form class="finder finder--inline" method="get" action="<?= e(url('admin/users.php')) ?>">
            <label class="field">
                <span>Search by name or email</span>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="kaveen">
            </label>

            <label class="field">
                <span>Role</span>
                <select name="role">
                    <option value="all"      <?= $role === 'all'      ? 'selected' : '' ?>>Everyone</option>
                    <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Drivers</option>
                    <option value="owner"    <?= $role === 'owner'    ? 'selected' : '' ?>>Operators</option>
                    <option value="admin"    <?= $role === 'admin'    ? 'selected' : '' ?>>Administrators</option>
                </select>
            </label>

            <label class="field">
                <span>Account state</span>
                <select name="status">
                    <option value="all"       <?= $status === 'all'       ? 'selected' : '' ?>>Any</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </label>

            <button class="btn btn--primary" type="submit">Apply</button>
        </form>

        <?php if (!$users): ?>
            <div class="empty">
                <h3>No accounts match</h3>
                <p>Try a different search or clear the filters.</p>
            </div>
        <?php else: ?>
            <p class="muted small"><?= count($users) ?> <?= count($users) === 1 ? 'account' : 'accounts' ?>.</p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th class="num">Bookings</th>
                            <th class="num">Car parks</th>
                            <th>Joined</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $person): ?>
                            <tr>
                                <td>
                                    <strong><?= e($person['full_name']) ?></strong>
                                    <?php if ((int) $person['user_id'] === $adminId): ?>
                                        <span class="muted small">(you)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= e($person['email']) ?><br>
                                    <span class="muted"><?= e($person['phone']) ?></span>
                                </td>
                                <td><?= e(ucfirst($person['role'])) ?></td>
                                <td class="num"><?= (int) $person['bookings'] ?></td>
                                <td class="num"><?= (int) $person['lots'] ?></td>
                                <td class="small"><?= e(date('d M Y', strtotime($person['created_at']))) ?></td>
                                <td>
                                    <span class="<?= e(status_class($person['status'])) ?>">
                                        <?= e(ucfirst($person['status'])) ?>
                                    </span>
                                </td>
                                <td class="nowrap">
                                    <?php if ((int) $person['user_id'] !== $adminId): ?>
                                        <form method="post" style="display:inline;margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
                                            <button class="btn btn--small btn--ghost" type="submit"
                                                data-confirm="<?= $person['status'] === 'active'
                                                    ? 'Suspend ' . e($person['full_name']) . '? They will be signed out.'
                                                    : 'Restore access for ' . e($person['full_name']) . '?' ?>">
                                                <?= $person['status'] === 'active' ? 'Suspend' : 'Restore' ?>
                                            </button>
                                        </form>

                                        <?php if ($person['role'] === 'customer' && (int) $person['bookings'] === 0): ?>
                                            <form method="post" style="display:inline;margin:0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="make_owner">
                                                <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
                                                <button class="btn btn--small btn--ghost" type="submit"
                                                        data-confirm="Turn this into a car park operator account?">
                                                    Make operator
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
