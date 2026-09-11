<?php
/**
 * ParkMate - create an account
 */

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(home_for_role(user_role()));
}

$errors = [];
$form = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'customer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['email']     = strtolower(trim($_POST['email'] ?? ''));
    $form['phone']     = trim($_POST['phone'] ?? '');
    $form['role']      = ($_POST['role'] ?? 'customer') === 'owner' ? 'owner' : 'customer';
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['password_confirm'] ?? '';

    if (mb_strlen($form['full_name']) < 3) {
        $errors['full_name'] = 'Give your full name, at least 3 characters.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like an email address.';
    }
    if (!preg_match('/^0\d{9}$/', $form['phone'])) {
        $errors['phone'] = 'Enter a 10-digit number starting with 0, like 0771234567.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $check = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([$form['email']]);
        if ($check->fetchColumn()) {
            $errors['email'] = 'An account already uses that email address.';
        }
    }

    if (!$errors) {
        $insert = db()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $form['full_name'],
            $form['email'],
            $form['phone'],
            password_hash($password, PASSWORD_DEFAULT),
            $form['role'],
        ]);

        $_SESSION['user_id'] = (int) db()->lastInsertId();
        flash('success', 'Welcome to ParkMate, ' . $form['full_name'] . '.');
        redirect(home_for_role($form['role']));
    }
}

$pageTitle = 'Create an account';
$navKey    = 'register';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">
        <div class="form-narrow">
            <h1>Create an account</h1>
            <p class="muted">It takes a minute, and you can book straight after.</p>

            <div class="card">
                <form method="post" class="stack" novalidate>
                    <?= csrf_field() ?>

                    <label class="field">
                        <span>Full name</span>
                        <input type="text" name="full_name" value="<?= e($form['full_name']) ?>" required>
                        <?php if (isset($errors['full_name'])): ?>
                            <p class="form-error"><?= e($errors['full_name']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($form['email']) ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <p class="form-error"><?= e($errors['email']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Mobile number</span>
                        <input type="tel" name="phone" value="<?= e($form['phone']) ?>"
                               placeholder="0771234567" required>
                        <?php if (isset($errors['phone'])): ?>
                            <p class="form-error"><?= e($errors['phone']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" data-password required>
                        <?php if (isset($errors['password'])): ?>
                            <p class="form-error"><?= e($errors['password']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Repeat password</span>
                        <input type="password" name="password_confirm" data-password-confirm required>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <p class="form-error"><?= e($errors['password_confirm']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>What will you use ParkMate for?</span>
                        <select name="role">
                            <option value="customer" <?= $form['role'] === 'customer' ? 'selected' : '' ?>>
                                Booking a bay for my vehicle
                            </option>
                            <option value="owner" <?= $form['role'] === 'owner' ? 'selected' : '' ?>>
                                Listing a car park I operate
                            </option>
                        </select>
                    </label>

                    <button class="btn btn--primary btn--block" type="submit">Create account</button>
                </form>
            </div>

            <p class="muted small" style="margin-top:14px">
                Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a>.
            </p>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
