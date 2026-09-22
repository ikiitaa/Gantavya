<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pidx = trim((string) ($_GET['pidx'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
if ($pidx === '' || $state === '') {
    http_response_code(400);
    exit('Invalid Khalti callback. No booking has been marked as paid.');
}

$stmt = $pdo->prepare(
    'SELECT p.*, b.booking_code, b.payment_status AS booking_payment_status
     FROM payments p JOIN bookings b ON b.id = p.booking_id
     WHERE p.pidx = ? LIMIT 1'
);
$stmt->execute([$pidx]);
$payment = $stmt->fetch();

if (!$payment || !hash_equals((string) $payment['callback_token_hash'], hash('sha256', $state))) {
    http_response_code(403);
    exit('The Khalti callback could not be matched to this booking. No booking has been marked as paid.');
}

try {
    $result = reconcile_khalti_payment($pdo, $payment);
    $resultKey = $result['status'];
} catch (Throwable $exception) {
    error_log('Khalti callback verification failed for pidx ' . $pidx . ': ' . $exception->getMessage());
    $resultKey = 'verification_error';
}

redirect(
    'payment-result.php?booking=' . rawurlencode((string) $payment['booking_code']) . '&result=' . rawurlencode($resultKey),
    303
);
