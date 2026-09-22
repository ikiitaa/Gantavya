<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    json_response(['success' => false, 'message' => 'POST requests only.'], 405);
}
verify_csrf();

$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '' || strlen($message) > 500) {
    json_response(['success' => false, 'message' => 'Enter a question using no more than 500 characters.'], 422);
}

$now = time();
$recentRequests = array_values(array_filter(
    is_array($_SESSION['chat_request_times'] ?? null) ? $_SESSION['chat_request_times'] : [],
    static function ($timestamp) use ($now): bool {
        return is_int($timestamp) && $timestamp >= $now - 60;
    }
));
if (count($recentRequests) >= 30) {
    json_response(['success' => false, 'message' => 'Please wait a moment before sending more questions.'], 429);
}
$recentRequests[] = $now;
$_SESSION['chat_request_times'] = $recentRequests;

$normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $message)));
$productRows = $pdo->query(
    'SELECT id, type, title, base_price, max_pax FROM products WHERE is_active = 1 ORDER BY type, base_price'
)->fetchAll();
$products = [];
foreach ($productRows as $product) {
    $products[$product['id']] = $product;
}

$aliases = [
    'scorpio' => 'v1', 'tourist bus' => 'v2', 'bus' => 'v2', 'premium car' => 'v3', 'car' => 'v3',
    'motorbike' => 'v4', 'bike' => 'v4', 'scooty' => 'v5', 'scooter' => 'v5', 'ev van' => 'v6',
    'everest base camp' => 't1', 'everest' => 't1', 'ebc' => 't1',
    'annapurna base camp' => 't2', 'annapurna' => 't2', 'abc' => 't2',
    'tilicho' => 't3', 'gosaikunda' => 't4', 'langtang' => 't5', 'dhorpatan' => 't6',
    'mardi' => 't7', 'panchpokhari' => 't8', 'bhairavkunda' => 't9',
    'thailand' => 'i1', 'dubai' => 'i2', 'switzerland' => 'i3', 'italy' => 'i4',
    'japan' => 'i5', 'uk' => 'i6', 'united kingdom' => 'i6', 'usa' => 'i7',
    'america' => 'i7', 'france' => 'i8', 'greece' => 'i9', 'china' => 'i10',
    'india' => 'i11', 'vietnam' => 'i12',
];

$matchedProduct = null;
foreach ($aliases as $alias => $productId) {
    if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $normalized) === 1 && isset($products[$productId])) {
        $matchedProduct = $products[$productId];
        break;
    }
}

$suggestions = ['How do I book?', 'How does Khalti payment work?', 'I need to talk to a person'];

if ($matchedProduct) {
    $unit = $matchedProduct['type'] === 'vehicle' ? 'per day' : 'per person';
    $reply = $matchedProduct['title'] . ' starts at ' . format_money($matchedProduct['base_price']) . ' ' . $unit
        . ' and accepts up to ' . (int) $matchedProduct['max_pax'] . ' traveler(s) in one booking.';
    if ($matchedProduct['type'] === 'trek') {
        $reply .= ' Groups of five or more receive NPR 4,000 off per trekker.';
    } elseif ($matchedProduct['type'] === 'intl') {
        $reply .= ' Groups of four or more receive a 5% discount.';
    }
    $reply .= ' Open its card, select the number of travelers, and press Book now to continue.';
    $suggestions = ['What details do I need?', 'How do I pay?', 'Show me other services'];
} elseif (preg_match('/\b(hello|hi|hey|namaste)\b/', $normalized)) {
    $reply = 'Namaste! I can help you compare trips, understand prices, complete a booking, track payment, or contact the Gantavya team.';
    $suggestions = ['Show me vehicle rentals', 'Show me trekking packages', 'Show me international tours'];
} elseif (preg_match('/\b(pending|processing|stuck)\b/', $normalized) && preg_match('/\b(payment|khalti|paid)\b/', $normalized)) {
    $reply = 'Open My Bookings and choose Check Khalti status. Do not start another payment while the first one is pending. If it remains pending, call 9745384731 for support.';
    $suggestions = ['How do I track my booking?', 'My payment failed', 'Call Gantavya'];
} elseif (preg_match('/\b(failed|cancelled|canceled|expired)\b/', $normalized) && preg_match('/\b(payment|khalti|paid)\b/', $normalized)) {
    $reply = 'Your booking should remain unpaid. Open My Bookings and use Pay or retry with Khalti when you are ready. A cancelled or failed payment is never treated as successful.';
    $suggestions = ['Open My Bookings', 'How do I pay?', 'Call Gantavya'];
} elseif (preg_match('/\b(khalti|payment|pay|wallet)\b/', $normalized)) {
    $reply = 'After you submit the booking form, you will continue to Khalti to complete payment. When you return, check My Bookings for the latest payment and booking status.';
    $suggestions = ['My payment is pending', 'My payment failed', 'How do I track my booking?'];
} elseif (preg_match('/\b(track|history|status|my bookings?|booking codes?)\b/', $normalized)) {
    $reply = 'Log in and open My Bookings from the top menu. You can see your booking code, travel date, pickup point, amount, booking status, and Khalti payment status there.';
    $suggestions = ['My payment is pending', 'Can I cancel a booking?', 'I need to talk to a person'];
} elseif (preg_match('/\b(cancel|change|reschedule|edit)\b/', $normalized)) {
    $reply = 'An unpaid booking can be cancelled from My Bookings when no payment is active. For a paid booking, date change, pickup change, or rescheduling, please call 9745384731.';
    $suggestions = ['How do I track my booking?', 'Call Gantavya', 'Show me services'];
} elseif (preg_match('/\b(vehicle|rental|rent|transport)\b/', $normalized)) {
    $available = array_values(array_filter($products, static function (array $product): bool { return $product['type'] === 'vehicle'; }));
    $names = array_map(static function (array $product): string { return $product['title']; }, $available);
    $reply = 'Available vehicle rentals include ' . implode(', ', $names) . '. Prices are charged per day. Select a vehicle card to choose rental days and travelers.';
    $suggestions = ['Scorpio price', 'Tourist bus price', 'EV van price'];
} elseif (preg_match('/\b(trek|trekking|hike|hiking|nepal trip)\b/', $normalized)) {
    $available = array_values(array_filter($products, static function (array $product): bool { return $product['type'] === 'trek'; }));
    $names = array_map(static function (array $product): string { return $product['title']; }, array_slice($available, 0, 6));
    $reply = 'Popular trekking options include ' . implode(', ', $names) . '. Open the Nepal Trekking section for every available route and group pricing.';
    $suggestions = ['Everest Base Camp price', 'Annapurna price', 'Langtang price'];
} elseif (preg_match('/\b(international|abroad|country|overseas|foreign)\b/', $normalized)) {
    $available = array_values(array_filter($products, static function (array $product): bool { return $product['type'] === 'intl'; }));
    $names = array_map(static function (array $product): string { return $product['title']; }, array_slice($available, 0, 7));
    $reply = 'International options include ' . implode(', ', $names) . ' and more. Tell me a country name to check its starting price.';
    $suggestions = ['Dubai price', 'Thailand price', 'Japan price'];
} elseif (preg_match('/\b(discount|group|offer|cheap|cheapest)\b/', $normalized)) {
    $reply = 'Trekking groups of five or more receive NPR 4,000 off per trekker. International groups of four or more receive 5% off. Vehicle totals depend on the selected rental days.';
    $suggestions = ['Show me trekking packages', 'Show me international tours', 'Show me vehicle rentals'];
} elseif (preg_match('/\b(detail|information|form|require|need to provide|pickup|date|phone)\b/', $normalized)) {
    $reply = 'The booking form asks for your name, email, phone number, travel date, pickup or meeting address, destination, number of travelers, and any special request.';
    $suggestions = ['How do I pay?', 'How do I track my booking?', 'Show me services'];
} elseif (preg_match('/\b(contact|person|human|staff|call|phone number|help line|helpline)\b/', $normalized)) {
    $reply = 'You can call the Gantavya team at 9745384731 or email info@gantavya.com for personal assistance.';
    $suggestions = ['Show me services', 'How do I book?', 'How do I track my booking?'];
} elseif (preg_match('/\b(service|services|option|options|explore)\b/', $normalized)) {
    $reply = 'Gantavya offers vehicle rentals, guided Nepal trekking packages, and international tours. Choose a category below or tell me a destination or vehicle name.';
    $suggestions = ['Show me vehicle rentals', 'Show me trekking packages', 'Show me international tours'];
} elseif (preg_match('/\b(book|booking|bookings|reserve|reservation)\b/', $normalized)) {
    $reply = 'Choose a service card, select travelers or rental days, and press Book now. Log in, complete the travel form, then continue to Khalti. Your booking will appear under My Bookings.';
    $suggestions = ['What details do I need?', 'How do I pay?', 'How do I track my booking?'];
} else {
    $reply = 'I can help with vehicle rentals, Nepal treks, international tours, prices, booking steps, Khalti payment, or booking status. Try asking about a destination or service name.';
    $suggestions = ['Show me vehicle rentals', 'Show me trekking packages', 'Show me international tours'];
}

json_response([
    'success' => true,
    'reply' => $reply,
    'suggestions' => $suggestions,
]);
