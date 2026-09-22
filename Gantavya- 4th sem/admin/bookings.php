<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$pageTitle = 'Bookings';

$allowedBooking = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$allowedPayment = ['unpaid', 'initiated', 'pending', 'paid', 'failed', 'refunded'];
$bookingFilter = in_array($_GET['booking_status'] ?? '', $allowedBooking, true) ? $_GET['booking_status'] : '';
$paymentFilter = in_array($_GET['payment_status'] ?? '', $allowedPayment, true) ? $_GET['payment_status'] : '';
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($bookingFilter !== '') { $where[] = 'b.booking_status = ?'; $params[] = $bookingFilter; }
if ($paymentFilter !== '') { $where[] = 'b.payment_status = ?'; $params[] = $paymentFilter; }
if ($search !== '') {
    $where[] = '(b.booking_code LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.product_title LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}
$sql = 'SELECT b.* FROM bookings b' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.created_at DESC LIMIT 250';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel filter-panel">
    <form method="get" class="filter-form">
        <label>Search<input type="search" name="q" value="<?= e($search) ?>" placeholder="Code, customer, phone, service"></label>
        <label>Booking status<select name="booking_status"><option value="">All</option><?php foreach ($allowedBooking as $value): ?><option value="<?= e($value) ?>" <?= $bookingFilter === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></label>
        <label>Payment status<select name="payment_status"><option value="">All</option><?php foreach ($allowedPayment as $value): ?><option value="<?= e($value) ?>" <?= $paymentFilter === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-light" href="bookings.php">Reset</a>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Operations</p><h2><?= count($bookings) ?> booking(s)</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code / date</th><th>Customer</th><th>Service / travel date</th><th>Total</th><th>Payment</th><th>Booking</th><th></th></tr></thead>
            <tbody>
            <?php if (!$bookings): ?><tr><td colspan="7" class="empty-cell">No bookings match these filters.</td></tr><?php endif; ?>
            <?php foreach ($bookings as $item): ?>
                <tr>
                    <td><strong><?= e($item['booking_code']) ?></strong><small><?= e(date('M j, Y g:i A', strtotime($item['created_at']))) ?></small></td>
                    <td><?= e($item['customer_name']) ?><small><?= e($item['customer_phone']) ?></small></td>
                    <td><?= e($item['product_title']) ?><small><?= e(date('M j, Y', strtotime($item['service_date']))) ?>, <?= e($item['metrics']) ?></small></td>
                    <td><?= e(format_money($item['total_amount'])) ?></td>
                    <td><span class="<?= e(status_class($item['payment_status'])) ?>"><?= e(ucfirst($item['payment_status'])) ?></span></td>
                    <td><span class="<?= e(status_class($item['booking_status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $item['booking_status']))) ?></span></td>
                    <td><a class="table-link" href="booking.php?id=<?= (int) $item['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
