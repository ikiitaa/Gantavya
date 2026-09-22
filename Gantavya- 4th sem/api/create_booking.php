<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    json_response(['success' => false, 'message' => 'POST requests only.'], 405);
}
$user = require_login(true);
verify_csrf();

$productId = trim((string) ($_POST['product_id'] ?? ''));
$customerName = trim((string) ($_POST['customer_name'] ?? ''));
$customerEmail = filter_var(strtolower(trim((string) ($_POST['customer_email'] ?? ''))), FILTER_VALIDATE_EMAIL);
$customerPhone = trim((string) ($_POST['customer_phone'] ?? ''));
$serviceDateInput = trim((string) ($_POST['service_date'] ?? ''));
$pickupAddress = trim((string) ($_POST['pickup_address'] ?? ''));
$destinationAddress = trim((string) ($_POST['destination_address'] ?? ''));
$specialRequests = trim((string) ($_POST['special_requests'] ?? ''));
$travelers = filter_var($_POST['travelers'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$days = filter_var($_POST['days'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$productStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
$productStmt->execute([$productId]);
$product = $productStmt->fetch();

$errors = [];
if (!$product) {
    $errors[] = 'The selected service is not available.';
}
if (strlen($customerName) < 2 || strlen($customerName) > 100) {
    $errors[] = 'Enter the passenger or customer name.';
}
if (!$customerEmail) {
    $errors[] = 'Enter a valid email address.';
}
if (!valid_nepal_phone($customerPhone)) {
    $errors[] = 'Enter a valid Nepal mobile number.';
}
if (strlen($pickupAddress) < 4 || strlen($pickupAddress) > 255) {
    $errors[] = 'Enter a complete pickup or meeting address.';
}
if (strlen($destinationAddress) < 2 || strlen($destinationAddress) > 255) {
    $errors[] = 'Enter the destination or drop-off point.';
}
if (!$travelers || !$days) {
    $errors[] = 'Travelers and days must be valid numbers.';
}
if (strlen($specialRequests) > 2000) {
    $errors[] = 'Special requests must be shorter than 2,000 characters.';
}

$serviceDate = DateTime::createFromFormat('Y-m-d', $serviceDateInput);
$dateErrors = DateTime::getLastErrors();
if (!$serviceDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
    $errors[] = 'Select a valid travel date.';
} else {
    $today = new DateTime('today');
    $latest = (clone $today)->modify('+2 years');
    if ($serviceDate < $today || $serviceDate > $latest) {
        $errors[] = 'Travel date must be between today and two years from now.';
    }
}

if ($product && $travelers) {
    if ($travelers > (int) $product['max_pax']) {
        $errors[] = 'This service allows at most ' . (int) $product['max_pax'] . ' traveler(s) per booking.';
    }
    if ($product['type'] === 'vehicle' && $days > 30) {
        $errors[] = 'Vehicle rental can be booked for up to 30 days online.';
    }
    if ($product['type'] !== 'vehicle') {
        $days = 1;
    }
}

if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

try {
    $totals = calculate_booking_total($product, (int) $travelers, (int) $days);
    $bookingCode = random_code();
    $endDate = null;
    if ($product['type'] === 'vehicle') {
        $endDate = (clone $serviceDate)->modify('+' . ((int) $days - 1) . ' days')->format('Y-m-d');
        $metrics = $days . ' day(s), ' . $travelers . ' traveler(s)';
    } elseif ($product['type'] === 'trek') {
        $metrics = $travelers . ' trekker(s)';
    } else {
        $metrics = $travelers . ' passenger(s)';
    }

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO bookings
         (booking_code, user_id, product_id, service_type, product_title, customer_name, customer_email,
          customer_phone, service_date, end_date, pickup_address, destination_address, travelers, days,
          metrics, unit_price, subtotal_amount, discount_amount, total_amount, special_requests)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $bookingCode,
        (int) $user['id'],
        $product['id'],
        $product['type'],
        $product['title'],
        $customerName,
        $customerEmail,
        normalize_phone($customerPhone),
        $serviceDate->format('Y-m-d'),
        $endDate,
        $pickupAddress,
        $destinationAddress,
        (int) $travelers,
        (int) $days,
        $metrics,
        $totals['unit_price'],
        $totals['subtotal'],
        $totals['discount'],
        $totals['total'],
        $specialRequests === '' ? null : $specialRequests,
    ]);
    $bookingId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE users SET phone = COALESCE(phone, ?) WHERE id = ?')
        ->execute([normalize_phone($customerPhone), (int) $user['id']]);
    $pdo->commit();

    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch();

    try {
        $payment = initiate_booking_payment($pdo, $booking, $product);
    } catch (Throwable $paymentError) {
        error_log('Khalti initiation failed for ' . $bookingCode . ': ' . $paymentError->getMessage());
        json_response([
            'success' => false,
            'booking_created' => true,
            'booking_code' => $bookingCode,
            'message' => 'Your booking was saved, but Khalti could not start. Open My Bookings to retry payment.',
            'redirect' => app_url('my-bookings.php'),
        ], 502);
    }

    json_response([
        'success' => true,
        'message' => 'Booking created. Continue on Khalti to complete payment.',
        'booking_code' => $bookingCode,
        'payment_url' => $payment['payment_url'],
    ], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Booking creation failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'The booking could not be created. Please try again.'], 500);
}
