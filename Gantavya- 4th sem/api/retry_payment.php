<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    json_response(['success' => false, 'message' => 'POST requests only.'], 405);
}
$user = require_login(true);
verify_csrf();

$bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$bookingId) {
    json_response(['success' => false, 'message' => 'Invalid booking.'], 422);
}

$stmt = $pdo->prepare(
    'SELECT b.*, p.id AS current_product_id, p.type AS current_product_type, p.base_price AS current_base_price,
            p.title AS current_product_title, p.description AS current_product_description, p.image AS current_product_image,
            p.max_pax AS current_max_pax, p.is_active AS current_is_active
     FROM bookings b JOIN products p ON p.id = b.product_id
     WHERE b.id = ? AND b.user_id = ? LIMIT 1'
);
$stmt->execute([$bookingId, (int) $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    json_response(['success' => false, 'message' => 'Booking not found.'], 404);
}
if ($booking['payment_status'] === 'paid') {
    json_response(['success' => false, 'message' => 'This booking is already paid.'], 409);
}
if ($booking['payment_status'] === 'pending') {
    json_response(['success' => false, 'message' => 'Khalti still reports this payment as pending. Check its status instead of starting another payment.'], 409);
}
if ($booking['booking_status'] === 'cancelled') {
    json_response(['success' => false, 'message' => 'A cancelled booking cannot be paid.'], 409);
}

$activeStmt = $pdo->prepare(
    "SELECT payment_url FROM payments
     WHERE booking_id = ? AND provider_status = 'Initiated' AND payment_url IS NOT NULL
       AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY id DESC LIMIT 1"
);
$activeStmt->execute([$bookingId]);
$activeUrl = $activeStmt->fetchColumn();
if (is_string($activeUrl) && $activeUrl !== '') {
    json_response(['success' => true, 'payment_url' => $activeUrl, 'message' => 'Continuing your active Khalti payment.']);
}

$product = [
    'id' => $booking['current_product_id'],
    'type' => $booking['current_product_type'],
    'base_price' => $booking['current_base_price'],
    'title' => $booking['current_product_title'],
    'description' => $booking['current_product_description'],
    'image' => $booking['current_product_image'],
    'max_pax' => $booking['current_max_pax'],
    'is_active' => $booking['current_is_active'],
];

try {
    $payment = initiate_booking_payment($pdo, $booking, $product);
    json_response([
        'success' => true,
        'message' => 'A new Khalti payment page is ready.',
        'payment_url' => $payment['payment_url'],
    ]);
} catch (Throwable $exception) {
    error_log('Payment retry failed for ' . $booking['booking_code'] . ': ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Khalti could not start. Check the server key and try again.'], 502);
}
