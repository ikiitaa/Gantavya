<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$pageTitle = 'Payments';

if (request_is_post()) {
    verify_csrf();
    $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$paymentId) {
        flash('error', 'Invalid payment attempt.');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            flash('error', 'Payment attempt not found.');
        } else {
            try {
                $result = reconcile_khalti_payment($pdo, $payment);
                flash('success', 'Khalti lookup completed. Provider status: ' . $result['provider_status'] . '.');
            } catch (Throwable $exception) {
                error_log('Admin Khalti lookup failed for payment ' . $paymentId . ': ' . $exception->getMessage());
                flash('error', 'Khalti lookup failed. Check the server key and connection. No payment was marked paid.');
            }
        }
    }
    $returnBooking = filter_var($_POST['booking_filter'] ?? null, FILTER_VALIDATE_INT);
    redirect('admin/payments.php' . ($returnBooking ? '?booking=' . $returnBooking : ''), 303);
}

$bookingFilter = filter_var($_GET['booking'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$statusFilter = trim((string) ($_GET['provider_status'] ?? ''));
$where = [];
$params = [];
if ($bookingFilter) { $where[] = 'p.booking_id = ?'; $params[] = $bookingFilter; }
if ($statusFilter !== '') { $where[] = 'p.provider_status = ?'; $params[] = $statusFilter; }
$sql = 'SELECT p.*, b.booking_code, b.customer_name, b.product_title, b.payment_status AS booking_payment_status
        FROM payments p JOIN bookings b ON b.id = p.booking_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel filter-panel">
    <form method="get" class="filter-form">
        <label>Booking ID<input type="number" name="booking" min="1" value="<?= e($bookingFilter ?: '') ?>"></label>
        <label>Khalti status<input type="text" name="provider_status" value="<?= e($statusFilter) ?>" placeholder="Completed, Pending..."></label>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-light" href="payments.php">Reset</a>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Provider audit log</p><h2><?= count($payments) ?> payment attempt(s)</h2></div></div>
    <p class="immutable-note">Recheck contacts Khalti’s lookup API. It never changes a payment to paid unless Khalti returns Completed with the matching PIDX, amount, and a transaction ID.</p>
    <div class="table-wrap"><table><thead><tr><th>Booking / attempt</th><th>Customer / service</th><th>Amount</th><th>Khalti identifiers</th><th>Status</th><th>Created / verified</th><th></th></tr></thead><tbody>
        <?php if (!$payments): ?><tr><td colspan="7" class="empty-cell">No payment attempts match these filters.</td></tr><?php endif; ?>
        <?php foreach ($payments as $payment): ?>
        <tr>
            <td><a class="table-link" href="booking.php?id=<?= (int) $payment['booking_id'] ?>"><?= e($payment['booking_code']) ?></a><small>Attempt #<?= (int) $payment['attempt_no'] ?></small></td>
            <td><?= e($payment['customer_name']) ?><small><?= e($payment['product_title']) ?></small></td>
            <td><?= e(format_money(((int) $payment['amount_paisa']) / 100)) ?></td>
            <td class="mono"><?= e($payment['pidx'] ?: 'No PIDX') ?><small><?= e($payment['transaction_id'] ?: 'No transaction ID') ?></small></td>
            <td><span class="<?= e(status_class(provider_status_to_internal($payment['provider_status']))) ?>"><?= e($payment['provider_status']) ?></span><small>Booking: <?= e($payment['booking_payment_status']) ?></small></td>
            <td><?= e(date('M j, Y g:i A', strtotime($payment['created_at']))) ?><small><?= $payment['verified_at'] ? 'Verified ' . e(date('M j, g:i A', strtotime($payment['verified_at']))) : 'Not verified' ?></small></td>
            <td><?php if ($payment['pidx']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input type="hidden" name="booking_filter" value="<?= e($bookingFilter ?: '') ?>"><button class="table-button" type="submit">Recheck Khalti</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
