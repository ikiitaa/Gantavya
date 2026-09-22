<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login(false);

$stmt = $pdo->prepare(
    'SELECT b.*, p.image,
            lp.id AS last_payment_id, lp.provider_status AS last_provider_status, lp.transaction_id,
            lp.created_at AS payment_created_at
     FROM bookings b
     JOIN products p ON p.id = b.product_id
     LEFT JOIN payments lp ON lp.id = (SELECT MAX(p2.id) FROM payments p2 WHERE p2.booking_id = b.id)
     WHERE b.user_id = ? ORDER BY b.created_at DESC'
);
$stmt->execute([(int) $user['id']]);
$bookings = $stmt->fetchAll();
$flashes = pull_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>My Bookings | Gantavya</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-bg">
<header class="site-header compact">
    <a class="brand" href="index.php">Gantavya<span>.</span></a>
    <nav class="main-nav">
        <a href="index.php">Explore</a>
        <?php if ($user['role'] === 'admin'): ?><a href="admin/index.php">Admin</a><?php endif; ?>
        <form action="api/logout.php" method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="nav-link-button" type="submit">Logout</button>
        </form>
    </nav>
</header>

<main class="dashboard-shell customer-dashboard">
    <div class="page-heading">
        <div>
            <p class="eyebrow"></p>
            <h1>My Bookings</h1>
            <p>View your booking details.</p>
        </div>
        <a class="btn btn-primary" href="index.php#services">Book another trip</a>
    </div>

    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php if (!$bookings): ?>
        <section class="empty-state">
            <h2>No bookings yet</h2>
            <p>Your travel bookings will appear here after you select a service.</p>
            <a class="btn btn-primary" href="index.php#services">Explore services</a>
        </section>
    <?php else: ?>
        <div class="booking-list">
            <?php foreach ($bookings as $booking): ?>
                <article class="booking-card">
                    <img src="<?= e($booking['image']) ?>" alt="<?= e($booking['product_title']) ?>">
                    <div class="booking-main">
                        <div class="booking-topline">
                            <div>
                                <span class="booking-code"><?= e($booking['booking_code']) ?></span>
                                <h2><?= e($booking['product_title']) ?></h2>
                            </div>
                            <div class="status-stack">
                                <span class="<?= e(status_class($booking['payment_status'])) ?>">Payment: <?= e(ucwords(str_replace('_', ' ', $booking['payment_status']))) ?></span>
                                <span class="<?= e(status_class($booking['booking_status'])) ?>">Booking: <?= e(ucwords(str_replace('_', ' ', $booking['booking_status']))) ?></span>
                            </div>
                        </div>

                        <div class="detail-grid">
                            <span><small>Travel date</small><?= e(date('M j, Y', strtotime($booking['service_date']))) ?></span>
                            <span><small>Travelers / scope</small><?= e($booking['metrics']) ?></span>
                            <span><small>Pickup / meeting point</small><?= e($booking['pickup_address']) ?></span>
                            <span><small>Destination</small><?= e($booking['destination_address']) ?></span>
                            <span><small>Customer</small><?= e($booking['customer_name']) ?>, <?= e($booking['customer_phone']) ?></span>
                            <span><small>Total</small><?= e(format_money($booking['total_amount'])) ?></span>
                        </div>

                        <?php if (!empty($booking['special_requests'])): ?>
                            <p class="note"><strong>Special request:</strong> <?= e($booking['special_requests']) ?></p>
                        <?php endif; ?>

                        <div class="button-row">
                            <?php if ($booking['payment_status'] === 'pending' && $booking['booking_status'] !== 'cancelled'): ?>
                                <button class="btn btn-primary check-payment" type="button" data-booking-id="<?= (int) $booking['id'] ?>">Check Khalti status</button>
                            <?php elseif (!in_array($booking['payment_status'], ['paid', 'refunded'], true) && $booking['booking_status'] !== 'cancelled'): ?>
                                <button class="btn btn-khalti retry-payment" type="button" data-booking-id="<?= (int) $booking['id'] ?>"><?= $booking['payment_status'] === 'initiated' ? 'Continue Khalti payment' : 'Pay or retry with Khalti' ?></button>
                            <?php endif; ?>
                            <?php if (in_array($booking['payment_status'], ['unpaid', 'failed'], true) && $booking['booking_status'] === 'pending'): ?>
                                <form action="api/cancel_booking.php" method="post" onsubmit="return confirm('Cancel this unpaid booking?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                    <button class="btn btn-light" type="submit">Cancel booking</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($booking['transaction_id'])): ?>
                                <span class="transaction-ref">Khalti transaction: <?= e($booking['transaction_id']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
document.querySelectorAll('.retry-payment').forEach(function (button) {
    button.addEventListener('click', async function () {
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Opening Khalti...';
        const form = new FormData();
        form.append('booking_id', button.dataset.bookingId);
        form.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        try {
            const response = await fetch('api/retry_payment.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Payment could not start.');
            window.location.href = data.payment_url;
        } catch (error) {
            alert(error.message);
            button.disabled = false;
            button.textContent = original;
        }
    });
});

document.querySelectorAll('.check-payment').forEach(function (button) {
    button.addEventListener('click', async function () {
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Checking Khalti...';
        const form = new FormData();
        form.append('booking_id', button.dataset.bookingId);
        form.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        try {
            const response = await fetch('api/check_payment.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Status check failed.');
            alert(data.message);
            window.location.href = data.redirect;
        } catch (error) {
            alert(error.message);
            button.disabled = false;
            button.textContent = original;
        }
    });
});
</script>
</body>
</html>
