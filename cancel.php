<?php
/**
 * ParkMate - cancel a booking
 *
 * Setting the status to 'cancelled' fires trg_reservation_after_update,
 * which refunds any payment and notifies the customer.
 */

require_once __DIR__ . '/config/config.php';

require_login();

$reservationId = (int) ($_GET['reservation_id'] ?? $_POST['reservation_id'] ?? 0);//?? mean If lot_id doesn't exist, use 0
$role = user_role();

$stmt = db()->prepare(
    'SELECT r.reservation_id, r.user_id, r.status, r.start_time, pl.owner_id
       FROM reservations r
       JOIN parking_slots ps ON ps.slot_id = r.slot_id
       JOIN parking_lots  pl ON pl.lot_id  = ps.lot_id
      WHERE r.reservation_id = ?'
);
$stmt->execute([$reservationId]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('error', 'That booking was not found.');
    redirect(home_for_role($role));
}

// A customer cancels their own; an owner cancels one in their car park.
$isOwnBooking = (int) $booking['user_id'] === user_id();
$isLotOwner   = (int) $booking['owner_id'] === user_id();

if (!($isOwnBooking || ($role === 'owner' && $isLotOwner) || $role === 'admin')) {
    http_response_code(403);
    flash('error', 'That booking is not yours to cancel.');
    redirect(home_for_role($role));
}

if (in_array($booking['status'], ['cancelled', 'completed'], true)) {
    flash('info', 'That booking is already closed.');
    redirect($role === 'customer' ? 'my-bookings.php' : 'owner/reservations.php');
}

// Customers cannot cancel once the window has started; owners still can.
if ($isOwnBooking && $role === 'customer' && strtotime($booking['start_time']) <= time()) {
    flash('error', 'This booking has already started, so it cannot be cancelled online.');
    redirect('my-bookings.php');
}

$update = db()->prepare(
    'UPDATE reservations SET status = "cancelled" WHERE reservation_id = ?'
);
$update->execute([$reservationId]);

flash('success', 'Booking cancelled. The bay is free again.');
redirect($role === 'customer' ? 'my-bookings.php' : 'owner/reservations.php');
