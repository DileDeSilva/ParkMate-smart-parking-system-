<?php
/**
 * ParkMate - pay for a booking
 *
 * The card form is a simulation. No card data is stored or sent anywhere:
 * the fields are validated for shape, then the payment row is marked paid.
 * A trigger on the payments table is what moves the reservation to
 * 'confirmed', so payment and confirmation can never drift apart.
 */

require_once __DIR__ . '/config/config.php';

require_role('customer');

$reservationId = (int) ($_GET['reservation_id'] ?? $_POST['reservation_id'] ?? 0);

$stmt = db()->prepare(
    'SELECT * FROM vw_reservation_details
      WHERE reservation_id = ? AND user_id = ?'
);
$stmt->execute([$reservationId, user_id()]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('error', 'That booking was not found.');
    redirect('my-bookings.php');
}

if ($booking['payment_status'] === 'paid') {
    flash('info', 'That booking is already paid for.');
    redirect('my-bookings.php');
}
if (in_array($booking['reservation_status'], ['cancelled', 'completed'], true)) {
    flash('error', 'That booking is closed and cannot be paid for.');
    redirect('my-bookings.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $method = $_POST['method'] ?? 'card';
    if (!in_array($method, ['card', 'wallet', 'cash'], true)) {
        $method = 'card';
    }

    if ($method === 'card') {
        $number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $expiry = trim($_POST['card_expiry'] ?? '');
        $cvv    = trim($_POST['card_cvv'] ?? '');
        $name   = trim($_POST['card_name'] ?? '');

        if (strlen($number) !== 16) {
            $errors['card_number'] = 'A card number is 16 digits.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            $errors['card_expiry'] = 'Use the MM/YY format printed on the card.';
        } else {
            [$mm, $yy] = explode('/', $expiry);
            $expiresAt = strtotime('20' . $yy . '-' . $mm . '-01 +1 month');
            if ($expiresAt !== false && $expiresAt < time()) {
                $errors['card_expiry'] = 'That card has expired.';
            }
        }
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors['card_cvv'] = 'The security code is 3 or 4 digits.';
        }
        if ($name === '') {
            $errors['card_name'] = 'Enter the name printed on the card.';
        }
    }

    if (!$errors) {
        // Marking the payment paid fires trg_payment_after_update, which
        // confirms the reservation and writes the customer a notification.
        $update = db()->prepare(
            'UPDATE payments
                SET status = "paid", method = ?, txn_reference = ?, paid_at = NOW()
              WHERE reservation_id = ? AND status = "pending"'
        );
        $update->execute([
            $method,
            'PM-' . strtoupper(bin2hex(random_bytes(4))),
            $reservationId,
        ]);

        if ($update->rowCount() === 0) {
            flash('error', 'That payment was already handled.');
            redirect('my-bookings.php');
        }

        flash('success', 'Payment received. Your bay is confirmed.');
        redirect('my-bookings.php');
    }
}

$pageTitle = 'Pay for booking';
$navKey    = 'bookings';
require __DIR__ . '/includes/header.php';
?>

<main class="page">
    <div class="shell">

        <div class="page-head">
            <div>
                <h1>Pay for your bay</h1>
                <p class="muted">
                    Booking #<?= (int) $booking['reservation_id'] ?> is held until you pay.
                </p>
            </div>
        </div>

        <div class="notice notice--info">
            This is a coursework prototype. Card details are checked for format
            only, then discarded. Do not enter a real card number.
        </div>

        <div class="grid grid--sidebar">
            <section class="card">
                <h2>Payment details</h2>

                <form method="post" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reservation_id" value="<?= (int) $booking['reservation_id'] ?>">

                    <label class="field">
                        <span>How would you like to pay?</span>
                        <select name="method">
                            <option value="card">Card</option>
                            <option value="wallet">ParkMate wallet</option>
                            <option value="cash">Cash on arrival</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Name on card</span>
                        <input type="text" name="card_name"
                               value="<?= e($_POST['card_name'] ?? '') ?>">
                        <?php if (isset($errors['card_name'])): ?>
                            <p class="form-error"><?= e($errors['card_name']) ?></p>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Card number</span>
                        <input type="text" name="card_number" inputmode="numeric"
                               placeholder="4111 1111 1111 1111" data-card-number
                               value="<?= e($_POST['card_number'] ?? '') ?>">
                        <?php if (isset($errors['card_number'])): ?>
                            <p class="form-error"><?= e($errors['card_number']) ?></p>
                        <?php endif; ?>
                    </label>

                    <div class="grid grid--2">
                        <label class="field">
                            <span>Expires</span>
                            <input type="text" name="card_expiry" placeholder="09/29" maxlength="5"
                                   value="<?= e($_POST['card_expiry'] ?? '') ?>">
                            <?php if (isset($errors['card_expiry'])): ?>
                                <p class="form-error"><?= e($errors['card_expiry']) ?></p>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Security code</span>
                            <input type="text" name="card_cvv" inputmode="numeric" maxlength="4"
                                   value="<?= e($_POST['card_cvv'] ?? '') ?>">
                            <?php if (isset($errors['card_cvv'])): ?>
                                <p class="form-error"><?= e($errors['card_cvv']) ?></p>
                            <?php endif; ?>
                        </label>
                    </div>

                    <button class="btn btn--primary" type="submit">
                        Pay <?= e(money($booking['total_amount'])) ?>
                    </button>
                </form>
            </section>

            <aside>
                <div class="summary">
                    <h3>What you are paying for</h3>
                    <dl>
                        <dt>Car park</dt><dd><?= e($booking['lot_name']) ?></dd>
                        <dt>Bay</dt><dd><?= e($booking['slot_code']) ?> &middot; <?= e($booking['floor_level']) ?></dd>
                        <dt>Vehicle</dt><dd><?= e($booking['plate_number']) ?></dd>
                        <dt>Arriving</dt><dd><?= e(dt($booking['start_time'])) ?></dd>
                        <dt>Leaving</dt><dd><?= e(dt($booking['end_time'])) ?></dd>
                        <dt>Charged</dt><dd><?= (int) $booking['billed_hours'] ?> hours</dd>
                    </dl>
                    <div class="summary__total">
                        <span>Total</span>
                        <b><?= e(money($booking['total_amount'])) ?></b>
                    </div>
                </div>

                <p class="muted small" style="margin-top:14px">
                    Changed your mind?
                    <a href="<?= e(url('cancel.php?reservation_id=' . (int) $booking['reservation_id'])) ?>"
                       data-confirm="Release this bay and cancel the booking?">Release this bay</a>.
                </p>
            </aside>
        </div>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
