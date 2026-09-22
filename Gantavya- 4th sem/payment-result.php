<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$bookingCode = trim((string) ($_GET['booking'] ?? ''));
$stmt = $pdo->prepare(
    'SELECT booking_code, product_title, service_date, total_amount, payment_status, booking_status
     FROM bookings WHERE booking_code = ? LIMIT 1'
);
$stmt->execute([$bookingCode]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

$paymentStatus = (string) $booking['payment_status'];
if ($paymentStatus === 'paid') {
    $tone = 'success';
    $title = 'Payment verified';
    $message = 'Khalti confirmed the payment. Your booking is now confirmed.';
} elseif ($paymentStatus === 'pending' || $paymentStatus === 'initiated') {
    $tone = 'warning';
    $title = 'Payment is still pending';
    $message = 'Khalti has not confirmed a completed payment. No service is confirmed yet. You can check again from My Bookings.';
} elseif ($paymentStatus === 'refunded') {
    $tone = 'warning';
    $title = 'Payment refunded';
    $message = 'Khalti reports this transaction as refunded. The booking is not active.';
} else {
    $tone = 'danger';
    $title = 'Payment not completed';
    $message = 'Khalti did not verify a completed payment. Your booking is saved and you can retry from My Bookings.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Gantavya</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-bg">
<main class="result-wrap">
    <section class="result-card result-<?= e($tone) ?>">
        <div class="result-icon" aria-hidden="true"><?= $tone === 'success' ? '✓' : ($tone === 'warning' ? '!' : '×') ?></div>
        <p class="eyebrow">Khalti payment status</p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <div class="result-summary">
            <span>Booking code<strong><?= e($booking['booking_code']) ?></strong></span>
            <span>Service<strong><?= e($booking['product_title']) ?></strong></span>
            <span>Travel date<strong><?= e(date('M j, Y', strtotime($booking['service_date']))) ?></strong></span>
            <span>Amount<strong><?= e(format_money($booking['total_amount'])) ?></strong></span>
        </div>
        <div class="button-row center">
            <a class="btn btn-primary" href="my-bookings.php">Open My Bookings</a>
            <a class="btn btn-light" href="index.php">Back to Home</a>
        </div>
    </section>
</main>
</body>
</html>
