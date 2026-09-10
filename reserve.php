<?php
/**
 * ParkMate - create a booking
 *
 * This is a POST-only handler. The real work happens in the stored
 * procedure sp_create_reservation, which locks the bay row and re-checks
 * for clashes inside a transaction, so two people confirming the same
 * bay at the same instant cannot both succeed.
 */

require_once __DIR__ . '/config/config.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('search.php');
}
csrf_verify();

$lotId     = (int) ($_POST['lot_id'] ?? 0);//?? mean If lot_id doesn't exist, use 0
$slotId    = (int) ($_POST['slot_id'] ?? 0);
$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
$start     = trim($_POST['start'] ?? '');
$end       = trim($_POST['end'] ?? '');

$backToLot = 'lot.php?id=' . $lotId . '&start=' . urlencode($start) . '&end=' . urlencode($end);

// ---------------------------------------------------------------------
// Validate before touching the database
// ---------------------------------------------------------------------
if ($slotId <= 0) {
    flash('error', 'Choose a bay before confirming.');
    redirect($backToLot);
}

$startTs = strtotime($start);
$endTs   = strtotime($end);

if ($startTs === false || $endTs === false || $endTs <= $startTs) {
    flash('error', 'That booking window is not valid.');
    redirect($backToLot);
}
if ($startTs < time() - 60) {
    flash('error', 'Pick a start time in the future.');
    redirect($backToLot);
}
if (($endTs - $startTs) > 7 * 24 * 3600) {
    flash('error', 'Bookings can run for at most seven days.');
    redirect($backToLot);
}

// The vehicle must belong to the person booking.
$check = db()->prepare('SELECT 1 FROM vehicles WHERE vehicle_id = ? AND user_id = ?');
$check->execute([$vehicleId, user_id()]);
if (!$check->fetchColumn()) {
    flash('error', 'Choose one of your own vehicles.');
    redirect($backToLot);
}

// The bay must belong to the lot the form came from.
$check = db()->prepare('SELECT 1 FROM parking_slots WHERE slot_id = ? AND lot_id = ?');
$check->execute([$slotId, $lotId]);
if (!$check->fetchColumn()) {
    flash('error', 'That bay is not in this car park.');
    redirect($backToLot);
}

// Stop the same vehicle being parked in two places at once.
$clash = db()->prepare(
    'SELECT COUNT(*) FROM reservations
      WHERE vehicle_id = :vehicle
        AND status IN ("pending","confirmed","active")
        AND :start_a < end_time
        AND :end_a   > start_time'
);
$clash->execute([
    'vehicle' => $vehicleId,
    'start_a' => date('Y-m-d H:i:s', $startTs),
    'end_a'   => date('Y-m-d H:i:s', $endTs),
]);
if ((int) $clash->fetchColumn() > 0) {
    flash('error', 'That vehicle already has a booking overlapping this window.');
    redirect($backToLot);
}

// ---------------------------------------------------------------------
// Hand off to the stored procedure
// ---------------------------------------------------------------------
$pdo = db();
$pdo->exec('SET @rid = 0, @msg = ""');

$call = $pdo->prepare('CALL sp_create_reservation(?, ?, ?, ?, ?, @rid, @msg)');
$call->execute([
    user_id(),
    $slotId,
    $vehicleId,
    date('Y-m-d H:i:s', $startTs),
    date('Y-m-d H:i:s', $endTs),
]);
$call->closeCursor();

$out = $pdo->query('SELECT @rid AS reservation_id, @msg AS message')->fetch();
$reservationId = (int) ($out['reservation_id'] ?? 0);
$message       = (string) ($out['message'] ?? '');

if ($reservationId <= 0) {
    flash('error', $message !== '' ? $message : 'The booking could not be made.');
    redirect($backToLot);
}

flash('success', $message);
redirect('payment.php?reservation_id=' . $reservationId);
