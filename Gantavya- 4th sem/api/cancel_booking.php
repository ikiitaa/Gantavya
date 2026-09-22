<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    http_response_code(405);
    exit('POST requests only.');
}
$user = require_login(false);
verify_csrf();

$bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$bookingId) {
    flash('error', 'Invalid booking.');
    redirect('my-bookings.php', 303);
}

$stmt = $pdo->prepare(
    "UPDATE bookings SET booking_status = 'cancelled', updated_at = NOW()
     WHERE id = ? AND user_id = ? AND payment_status IN ('unpaid', 'failed') AND booking_status = 'pending'"
);
$stmt->execute([$bookingId, (int) $user['id']]);

if ($stmt->rowCount() === 1) {
    flash('success', 'The unpaid booking was cancelled.');
} else {
    flash('error', 'This booking cannot be cancelled online while a payment is active, pending, or already paid.');
}
redirect('my-bookings.php', 303);
