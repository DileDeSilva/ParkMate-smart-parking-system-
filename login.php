<?php
/**
 * ParkMate - sign in
 */

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(home_for_role(user_role()));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare(
        'SELECT user_id, full_name, password_hash, role, status
           FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    // One message for both cases, so the form cannot be used to find
    // out which email addresses are registered.
    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'That email and password do not match an account.';
    } elseif ($row['status'] !== 'active') {
        $error = 'This account is suspended. Contact ParkMate support.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['user_id'];

        flash('success', 'Signed in as ' . $row['full_name'] . '.');

        $back = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);
        if ($back !== '' && $row['role'] === 'customer') {
            header('Location: ' . $back);
            exit;
        }
        redirect(home_for_role($row['role']));
    }
}

$pageTitle = 'Sign in';
$navKey    = 'login';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">
        <div class="form-narrow">
            <h1>Sign in</h1>
            <p class="muted">Pick up where you left off.</p>

            <?php if ($error): ?>
                <div class="notice notice--error"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="post" class="stack">
                    <?= csrf_field() ?>

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" required>
                    </label>

                    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
                </form>
            </div>

            <p class="muted small" style="margin-top:14px">
                No account yet? <a href="<?= e(url('register.php')) ?>">Create one</a>.
            </p>

            <div class="card" style="margin-top:22px;background:#f7f8f9">
                <h4 style="margin-bottom:8px">Demo logins</h4>
                <table style="font-size:.86rem">
                    <tr><td>Customer</td><td>kaveen@gmail.com</td><td>user123</td></tr>
                    <tr><td>Car park owner</td><td>nimal@parkmate.lk</td><td>owner123</td></tr>
                    <tr><td>Administrator</td><td>admin@parkmate.lk</td><td>admin123</td></tr>
                </table>
                <p class="muted small" style="margin:10px 0 0">
                    
                </p>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
