<?php
/**
 * ParkMate - page header.
 * Set $pageTitle and optionally $navKey before including this file.
 */

$pageTitle = $pageTitle ?? APP_NAME;
$navKey    = $navKey ?? '';
$user      = current_user();

/** Mark the nav item matching the current page. */
function nav_current(string $key, string $navKey): string
{
    return $key === $navKey ? ' aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<meta name="description" content="Reserve a parking bay before you leave, and pay for it online.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>

<header class="masthead">
    <div class="shell masthead__inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand__plate" aria-hidden="true">P</span>
            <?= e(APP_NAME) ?>
        </a>

        <nav class="nav" aria-label="Main">
            <a href="<?= e(url('search.php')) ?>"<?= nav_current('search', $navKey) ?>>Find parking</a>

            <?php if (!$user): ?>
                <a href="<?= e(url('login.php')) ?>"<?= nav_current('login', $navKey) ?>>Sign in</a>
                <a href="<?= e(url('register.php')) ?>"<?= nav_current('register', $navKey) ?>>Create account</a>

            <?php elseif ($user['role'] === 'customer'): ?>
                <a href="<?= e(url('dashboard.php')) ?>"<?= nav_current('dashboard', $navKey) ?>>
                    My parking
                    <?php $unread = unread_notification_count((int) $user['user_id']); ?>
                    <?php if ($unread > 0): ?>
                        <span class="nav__badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= e(url('my-bookings.php')) ?>"<?= nav_current('bookings', $navKey) ?>>Bookings</a>
                <a href="<?= e(url('vehicles.php')) ?>"<?= nav_current('vehicles', $navKey) ?>>Vehicles</a>

            <?php elseif ($user['role'] === 'owner'): ?>
                <a href="<?= e(url('owner/dashboard.php')) ?>"<?= nav_current('o-dash', $navKey) ?>>Dashboard</a>
                <a href="<?= e(url('owner/lots.php')) ?>"<?= nav_current('o-lots', $navKey) ?>>My car parks</a>
                <a href="<?= e(url('owner/reservations.php')) ?>"<?= nav_current('o-res', $navKey) ?>>Bookings</a>

            <?php else: ?>
                <a href="<?= e(url('admin/dashboard.php')) ?>"<?= nav_current('a-dash', $navKey) ?>>Dashboard</a>
                <a href="<?= e(url('admin/users.php')) ?>"<?= nav_current('a-users', $navKey) ?>>Users</a>
                <a href="<?= e(url('admin/lots.php')) ?>"<?= nav_current('a-lots', $navKey) ?>>Car parks</a>
            <?php endif; ?>
        </nav>

        <div class="spacer"></div>

        <?php if ($user): ?>
            <span class="whoami"><?= e($user['full_name']) ?></span>
            <a class="btn btn--small btn--ghost" href="<?= e(url('logout.php')) ?>"
               style="background:transparent;color:#cfd4da;border-color:#4a525b">Sign out</a>
        <?php endif; ?>
    </div>
</header>

<?php foreach (take_flashes() as $msg): ?>
    <div class="shell" style="padding-top:16px">
        <div class="notice notice--<?= e($msg['type']) ?>" role="status"><?= e($msg['message']) ?></div>
    </div>
<?php endforeach; ?>
