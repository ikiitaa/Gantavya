<?php
declare(strict_types=1);

function initiate_booking_payment(PDO $pdo, array $booking, array $product): array
{
    $attemptStmt = $pdo->prepare('SELECT COALESCE(MAX(attempt_no), 0) + 1 FROM payments WHERE booking_id = ?');
    $attemptStmt->execute([(int) $booking['id']]);
    $attemptNo = (int) $attemptStmt->fetchColumn();
    $purchaseOrderId = $booking['booking_code'] . '-A' . $attemptNo;
    $callbackToken = bin2hex(random_bytes(32));
    $callbackHash = hash('sha256', $callbackToken);
    $amountPaisa = (int) round((float) $booking['total_amount'] * 100);

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            "INSERT INTO payments
             (booking_id, provider, attempt_no, purchase_order_id, callback_token_hash, amount_paisa, provider_status)
             VALUES (?, 'khalti', ?, ?, ?, ?, 'Initiated')"
        );
        $insert->execute([
            (int) $booking['id'],
            $attemptNo,
            $purchaseOrderId,
            $callbackHash,
            $amountPaisa,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $returnUrl = app_url('khalti-callback.php') . '?state=' . rawurlencode($callbackToken);
    $customerInfo = [
        'name' => (string) $booking['customer_name'],
        'email' => (string) $booking['customer_email'],
        'phone' => normalize_phone((string) $booking['customer_phone']),
    ];

    $payload = [
        'return_url' => $returnUrl,
        'website_url' => app_url(),
        'amount' => $amountPaisa,
        'purchase_order_id' => $purchaseOrderId,
        'purchase_order_name' => (string) $booking['product_title'],
        'customer_info' => $customerInfo,
        'amount_breakdown' => [
            ['label' => 'Booking total', 'amount' => $amountPaisa],
        ],
        'product_details' => [[
            'identity' => (string) $product['id'],
            'name' => (string) $booking['product_title'],
            'total_price' => $amountPaisa,
            'quantity' => 1,
            'unit_price' => $amountPaisa,
        ]],
        'merchant_booking_code' => (string) $booking['booking_code'],
    ];

    try {
        $response = khalti_initiate($payload);
        if (empty($response['pidx']) || empty($response['payment_url'])) {
            throw new KhaltiException('Khalti did not return a payment page.');
        }
        $paymentUrl = (string) $response['payment_url'];
        $paymentHost = (string) parse_url($paymentUrl, PHP_URL_HOST);
        $paymentScheme = strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME));
        if ($paymentScheme !== 'https' || preg_match('/(^|\.)khalti\.com$/i', $paymentHost) !== 1) {
            throw new KhaltiException('Khalti returned an invalid payment page URL.');
        }

        $expiresAt = null;
        if (!empty($response['expires_at'])) {
            try {
                $expiresAt = (new DateTime((string) $response['expires_at']))->format('Y-m-d H:i:s');
            } catch (Throwable $ignored) {
                $expiresAt = null;
            }
        }
        if ($expiresAt === null && !empty($response['expires_in']) && (int) $response['expires_in'] > 0) {
            $expiresAt = (new DateTime())->modify('+' . (int) $response['expires_in'] . ' seconds')->format('Y-m-d H:i:s');
        }

        $update = $pdo->prepare(
            'UPDATE payments SET pidx = ?, payment_url = ?, expires_at = ?, raw_response = ?, updated_at = NOW() WHERE id = ?'
        );
        $update->execute([
            (string) $response['pidx'],
            $paymentUrl,
            $expiresAt,
            json_encode($response, JSON_UNESCAPED_SLASHES),
            $paymentId,
        ]);
        $pdo->prepare("UPDATE bookings SET payment_status = 'initiated', updated_at = NOW() WHERE id = ? AND payment_status <> 'paid'")
            ->execute([(int) $booking['id']]);

        return [
            'payment_id' => $paymentId,
            'pidx' => (string) $response['pidx'],
            'payment_url' => $paymentUrl,
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $exception) {
        $errorPayload = $exception instanceof KhaltiException ? $exception->responseData() : [];
        $pdo->prepare("UPDATE payments SET provider_status = 'Initiation failed', raw_response = ?, updated_at = NOW() WHERE id = ?")
            ->execute([json_encode($errorPayload, JSON_UNESCAPED_SLASHES), $paymentId]);
        $pdo->prepare("UPDATE bookings SET payment_status = 'failed', updated_at = NOW() WHERE id = ? AND payment_status <> 'paid'")
            ->execute([(int) $booking['id']]);
        throw $exception;
    }
}

function reconcile_khalti_payment(PDO $pdo, array $payment): array
{
    $pidx = trim((string) ($payment['pidx'] ?? ''));
    if ($pidx === '') {
        throw new RuntimeException('This payment attempt has no Khalti payment identifier.');
    }

    $lookup = khalti_lookup($pidx);
    $providerStatus = (string) ($lookup['status'] ?? 'Unknown');
    $internalStatus = provider_status_to_internal($providerStatus);
    $expectedAmount = (int) $payment['amount_paisa'];
    $returnedAmount = (int) ($lookup['total_amount'] ?? -1);
    $transactionId = trim((string) ($lookup['transaction_id'] ?? ''));

    $knownStatuses = ['completed', 'pending', 'initiated', 'refunded', 'partially refunded', 'expired', 'user canceled', 'failed'];
    $matchingReference = isset($lookup['pidx']) && hash_equals($pidx, (string) $lookup['pidx']);
    if (!in_array(strtolower($providerStatus), $knownStatuses, true) || !$matchingReference || $returnedAmount !== $expectedAmount) {
        throw new RuntimeException('Khalti lookup returned mismatched or incomplete payment data.');
    }

    if ($internalStatus === 'paid') {
        $valid = $transactionId !== '';
        if (!$valid) {
            throw new RuntimeException('Khalti returned completed status with mismatched payment data.');
        }
    }

    $pdo->beginTransaction();
    try {
        $lockedStmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $lockedStmt->execute([(int) $payment['id']]);
        $locked = $lockedStmt->fetch();
        if (!$locked) {
            throw new RuntimeException('Payment record not found.');
        }

        $updatePayment = $pdo->prepare(
            'UPDATE payments
             SET provider_status = ?, transaction_id = COALESCE(NULLIF(?, \'\'), transaction_id), raw_response = ?,
                 verified_at = CASE WHEN ? = \'Completed\' THEN NOW() ELSE verified_at END, updated_at = NOW()
             WHERE id = ?'
        );
        $updatePayment->execute([
            $providerStatus,
            $transactionId,
            json_encode($lookup, JSON_UNESCAPED_SLASHES),
            $providerStatus,
            (int) $payment['id'],
        ]);

        if ($internalStatus === 'paid') {
            $bookingUpdate = $pdo->prepare(
                "UPDATE bookings SET payment_status = 'paid', booking_status = CASE WHEN booking_status IN ('pending', 'cancelled') THEN 'confirmed' ELSE booking_status END, paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = ?"
            );
        } elseif ($internalStatus === 'refunded') {
            $bookingUpdate = $pdo->prepare(
                "UPDATE bookings SET payment_status = 'refunded', booking_status = 'cancelled', updated_at = NOW() WHERE id = ?"
            );
        } elseif ($internalStatus === 'pending') {
            $bookingUpdate = $pdo->prepare(
                "UPDATE bookings SET payment_status = 'pending', updated_at = NOW() WHERE id = ? AND payment_status <> 'paid'"
            );
        } else {
            $bookingUpdate = $pdo->prepare(
                "UPDATE bookings SET payment_status = 'failed', updated_at = NOW() WHERE id = ? AND payment_status <> 'paid'"
            );
        }
        $bookingUpdate->execute([(int) $payment['booking_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return ['status' => $internalStatus, 'provider_status' => $providerStatus, 'lookup' => $lookup];
}
