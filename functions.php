<?php
/**
 * ParkMate - shared helper functions
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Output and navigation
// ---------------------------------------------------------------------

/** Escape a value for safe printing inside HTML. Use on every echo. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an absolute URL from an app-relative path. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Send the browser somewhere else and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// ---------------------------------------------------------------------
// Flash messages - one-shot notices carried across a redirect
// ---------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pull all pending messages and clear them. */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Print the hidden field that every form in this app must include. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request unless the posted token matches the session token. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('That form expired. Go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

function money(float|string|null $amount): string
{
    return CURRENCY . ' ' . number_format((float) ($amount ?? 0), 2);
}

/** 05 Sep 2026, 2:30 PM */
function dt(?string $mysqlDateTime): string
{
    if (!$mysqlDateTime) {
        return '-';
    }
    return date('d M Y, g:i A', strtotime($mysqlDateTime));
}

/** 2:30 PM */
function tm(?string $mysqlTime): string
{
    if (!$mysqlTime) {
        return '-';
    }
    return date('g:i A', strtotime($mysqlTime));
}

/** Turn a status into a CSS modifier class. */
function status_class(string $status): string
{
    return 'tag tag--' . preg_replace('/[^a-z]/', '', strtolower($status));
}

// ---------------------------------------------------------------------
// Booking window helpers
// ---------------------------------------------------------------------

/**
 * Read a start/end window out of the query string, falling back to the
 * next whole hour for two hours. Returns MySQL-format datetimes.
 */
function requested_window(): array
{
    $start = $_GET['start'] ?? '';
    $end   = $_GET['end'] ?? '';

    $startTs = $start !== '' ? strtotime($start) : false;
    $endTs   = $end   !== '' ? strtotime($end)   : false;

    if ($startTs === false) {
        $startTs = (int) (ceil(time() / 3600) * 3600);
    }
    if ($endTs === false || $endTs <= $startTs) {
        $endTs = $startTs + 2 * 3600;
    }

    return [date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)];
}

/** Format a MySQL datetime for a <input type="datetime-local"> value. */
function input_dt(string $mysqlDateTime): string
{
    return date('Y-m-d\TH:i', strtotime($mysqlDateTime));
}

/** Whole hours in a window, rounded up, matching how the DB bills it. */
function billed_hours(string $start, string $end): int
{
    $minutes = (strtotime($end) - strtotime($start)) / 60;
    return max(1, (int) ceil($minutes / 60));
}

/**
 * Every bay in a lot, tagged with whether it is free for the given window.
 * The clash test mirrors sp_check_availability: two windows overlap when
 * start_a < end_b AND end_a > start_b.
 */
function slots_for_window(int $lotId, string $start, string $end): array
{
    $sql = "SELECT ps.slot_id,
                   ps.slot_code,
                   ps.floor_level,
                   ps.hourly_rate,
                   ps.status,
                   st.type_name,
                   (SELECT COUNT(*)
                      FROM reservations r
                     WHERE r.slot_id = ps.slot_id
                       AND r.status IN ('pending','confirmed','active')
                       AND :start_a < r.end_time
                       AND :end_a   > r.start_time) AS clashes
              FROM parking_slots ps
              JOIN slot_types st ON st.type_id = ps.type_id
             WHERE ps.lot_id = :lot_id
          ORDER BY ps.floor_level, ps.slot_code";

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'start_a' => $start,
        'end_a'   => $end,
        'lot_id'  => $lotId,
    ]);

    $slots = $stmt->fetchAll();
    foreach ($slots as &$slot) {
        $slot['is_free'] = ($slot['status'] === 'available' && (int) $slot['clashes'] === 0);
    }
    return $slots;
}

/** How many bays each lot has free for a window, keyed by lot_id. */
function free_counts_for_window(string $start, string $end): array
{
    $sql = "SELECT ps.lot_id,
                   COUNT(*) AS free_slots
              FROM parking_slots ps
             WHERE ps.status = 'available'
               AND NOT EXISTS (
                     SELECT 1 FROM reservations r
                      WHERE r.slot_id = ps.slot_id
                        AND r.status IN ('pending','confirmed','active')
                        AND :start_a < r.end_time
                        AND :end_a   > r.start_time)
          GROUP BY ps.lot_id";

    $stmt = db()->prepare($sql);
    $stmt->execute(['start_a' => $start, 'end_a' => $end]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['lot_id']] = (int) $row['free_slots'];
    }
    return $counts;
}

// ---------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
