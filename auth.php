<?php
/**
 * ParkMate - authentication and access control
 */

declare(strict_types=1);

/** The signed-in user row, or null. Cached for the request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT user_id, full_name, email, phone, role, status
           FROM users WHERE user_id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    // A suspended or deleted account loses its session immediately.
    if (!$row || $row['status'] !== 'active') {
        session_destroy();
        return null;
    }

    $user = $row;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['user_id'] : null;
}

/** Send anyone who is not signed in to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('error', 'Sign in to continue.');
        redirect('login.php');
    }
}

/** Restrict a page to one or more roles. */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(user_role(), $roles, true)) {
        http_response_code(403);
        flash('error', 'That area is not open to your account.');
        redirect(home_for_role(user_role()));
    }
}

/** Where each role lands after signing in. */
function home_for_role(?string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'owner' => 'owner/dashboard.php',
        default => 'dashboard.php',
    };
}

/** Confirm the signed-in owner actually owns this lot. */
function owns_lot(int $lotId): bool
{
    if (user_role() === 'admin') {
        return true;
    }
    $stmt = db()->prepare('SELECT owner_id FROM parking_lots WHERE lot_id = ?');
    $stmt->execute([$lotId]);
    $ownerId = $stmt->fetchColumn();

    return $ownerId !== false && (int) $ownerId === user_id();
}
