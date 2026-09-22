<?php
declare(strict_types=1);

/**
 * Calculates totals only from trusted product data fetched from the database.
 */
function calculate_booking_total(array $product, int $travelers, int $days): array
{
    $basePrice = round((float) $product['base_price'], 2);
    $type = (string) $product['type'];

    if ($basePrice <= 0 || $travelers < 1 || $days < 1) {
        throw new InvalidArgumentException('Invalid booking values.');
    }

    $subtotal = 0.0;
    $discount = 0.0;

    if ($type === 'vehicle') {
        $subtotal = $basePrice * $days;
    } elseif ($type === 'trek') {
        $subtotal = $basePrice * $travelers;
        if ($travelers >= 5) {
            $discount = min(4000.0, $basePrice) * $travelers;
        }
    } elseif ($type === 'intl') {
        $subtotal = $basePrice * $travelers;
        if ($travelers >= 4) {
            $discount = round($subtotal * 0.05, 2);
        }
    } else {
        throw new InvalidArgumentException('Unsupported service type.');
    }

    $total = round($subtotal - $discount, 2);
    if ($total < 10) {
        throw new InvalidArgumentException('The payable total must be at least NPR 10.');
    }

    return [
        'unit_price' => $basePrice,
        'subtotal' => round($subtotal, 2),
        'discount' => round($discount, 2),
        'total' => $total,
        'amount_paisa' => (int) round($total * 100),
    ];
}
