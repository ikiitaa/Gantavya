<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/booking_calculator.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$tests = [
    'vehicle days' => [
        ['type' => 'vehicle', 'base_price' => 7500], 2, 3, 22500.00,
    ],
    'trek group discount' => [
        ['type' => 'trek', 'base_price' => 20000], 5, 1, 80000.00,
    ],
    'international group discount' => [
        ['type' => 'intl', 'base_price' => 100000], 4, 1, 380000.00,
    ],
    'no international discount below four' => [
        ['type' => 'intl', 'base_price' => 100000], 3, 1, 300000.00,
    ],
];

$failed = 0;
foreach ($tests as $name => [$product, $travelers, $days, $expected]) {
    $result = calculate_booking_total($product, $travelers, $days);
    if (abs($result['total'] - $expected) > 0.001) {
        $failed++;
        echo "FAIL: {$name}. Expected {$expected}, received {$result['total']}\n";
    } else {
        echo "PASS: {$name}\n";
    }
}

$statusTests = [
    'Completed' => 'paid',
    'Pending' => 'pending',
    'Initiated' => 'pending',
    'Partially refunded' => 'refunded',
    'User canceled' => 'failed',
    'Expired' => 'failed',
];
foreach ($statusTests as $providerStatus => $expected) {
    $actual = provider_status_to_internal($providerStatus);
    if ($actual !== $expected) {
        $failed++;
        echo "FAIL: {$providerStatus} should map to {$expected}, received {$actual}\n";
    } else {
        echo "PASS: Khalti {$providerStatus} maps to {$expected}\n";
    }
}

if (!valid_nepal_phone('9800000000') || !valid_nepal_phone('+977 9800000000') || valid_nepal_phone('12345')) {
    $failed++;
    echo "FAIL: Nepal phone validation\n";
} else {
    echo "PASS: Nepal phone validation\n";
}

exit($failed === 0 ? 0 : 1);
