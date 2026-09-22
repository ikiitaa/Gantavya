<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$pageTitle = 'Dashboard';

$stats = [
    'bookings' => (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
    'paid' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid'")->fetchColumn(),
    'customers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn(),
];
$upcoming = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE service_date >= CURDATE() AND booking_status IN ('confirmed','in_progress')")->fetchColumn();
$pendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status IN ('initiated','pending')")->fetchColumn();
$recent = $pdo->query('SELECT id, booking_code, customer_name, product_title, total_amount, payment_status, booking_status, created_at FROM bookings ORDER BY created_at DESC LIMIT 8')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="stat-grid">
    <article><small>Total bookings</small><strong><?= $stats['bookings'] ?></strong><span>All recorded bookings</span></article>
    <article><small>Paid bookings</small><strong><?= $stats['paid'] ?></strong><span>Verified by Khalti</span></article>
    <article><small>Verified revenue</small><strong><?= e(format_money($stats['revenue'])) ?></strong><span>Paid bookings only</span></article>
    <article><small>Customers</small><strong><?= $stats['customers'] ?></strong><span>Registered accounts</span></article>
</section>

<section class="quick-grid">
    <a href="bookings.php?booking_status=confirmed"><strong><?= $upcoming ?></strong><span>Upcoming confirmed trips</span></a>
    <a href="payments.php?provider_status=Pending"><strong><?= $pendingPayments ?></strong><span>Payments needing attention</span></a>
    <a href="products.php"><strong><?= (int) $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn() ?></strong><span>Active services</span></a>
</section>

<section class="admin-panel">
    <div class="panel-heading"><div><p>Latest activity</p><h2>Recent bookings</h2></div><a href="bookings.php">View all</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Total</th><th>Payment</th><th>Booking</th><th></th></tr></thead>
            <tbody>
            <?php if (!$recent): ?><tr><td colspan="7" class="empty-cell">No bookings have been created.</td></tr><?php endif; ?>
            <?php foreach ($recent as $item): ?>
                <tr>
                    <td><strong><?= e($item['booking_code']) ?></strong><small><?= e(date('M j, Y', strtotime($item['created_at']))) ?></small></td>
                    <td><?= e($item['customer_name']) ?></td>
                    <td><?= e($item['product_title']) ?></td>
                    <td><?= e(format_money($item['total_amount'])) ?></td>
                    <td><span class="<?= e(status_class($item['payment_status'])) ?>"><?= e(ucfirst($item['payment_status'])) ?></span></td>
                    <td><span class="<?= e(status_class($item['booking_status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $item['booking_status']))) ?></span></td>
                    <td><a class="table-link" href="booking.php?id=<?= (int) $item['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
