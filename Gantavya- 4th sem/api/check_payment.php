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
    'SELECT p.*, b.booking_code
     FROM payments p JOIN bookings b ON b.id = p.booking_id
     WHERE b.id = ? AND b.user_id = ? AND p.pidx IS NOT NULL
     ORDER BY p.id DESC LIMIT 1'
);
$stmt->execute([$bookingId, (int) $user['id']]);
$payment = $stmt->fetch();
if (!$payment) {
    json_response(['success' => false, 'message' => 'No Khalti payment attempt is available to check.'], 404);
}

try {
    $result = reconcile_khalti_payment($pdo, $payment);
    $messages = [
        'paid' => 'Khalti confirmed the payment.',
        'pending' => 'Khalti still reports this payment as pending. Do not pay again.',
        'refunded' => 'Khalti reports this payment as refunded.',
        'failed' => 'Khalti reports that this payment was not completed. You may start a new attempt.',
    ];
    json_response([
        'success' => true,
        'status' => $result['status'],
        'message' => $messages[$result['status']] ?? 'Khalti status checked.',
        'redirect' => app_url('payment-result.php?booking=' . rawurlencode((string) $payment['booking_code']) . '&result=' . rawurlencode($result['status'])),
    ]);
} catch (Throwable $exception) {
    error_log('Customer Khalti lookup failed for payment ' . $payment['id'] . ': ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Khalti status could not be checked. No payment status was changed.'], 502);
}
