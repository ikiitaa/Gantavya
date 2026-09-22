<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$bookingId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$bookingId) { http_response_code(404); exit('Booking not found.'); }

$allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if (request_is_post()) {
    verify_csrf();
    $status = (string) ($_POST['booking_status'] ?? '');
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
    if (!in_array($status, $allowedStatuses, true)) {
        flash('error', 'Invalid booking status.');
    } elseif (strlen($adminNote) > 3000) {
        flash('error', 'Admin note is too long.');
    } else {
        $stmt = $pdo->prepare('UPDATE bookings SET booking_status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $adminNote === '' ? null : $adminNote, $bookingId]);
        flash('success', 'Booking operations status updated. Payment status was not changed.');
    }
    redirect('admin/booking.php?id=' . $bookingId, 303);
}

$stmt = $pdo->prepare('SELECT b.*, u.email AS account_email, p.image FROM bookings b JOIN users u ON u.id = b.user_id JOIN products p ON p.id = b.product_id WHERE b.id = ? LIMIT 1');
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) { http_response_code(404); exit('Booking not found.'); }
$payStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY attempt_no DESC');
$payStmt->execute([$bookingId]);
$payments = $payStmt->fetchAll();
$pageTitle = 'Booking ' . $booking['booking_code'];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-actions"><a class="btn btn-light" href="bookings.php">Back to bookings</a></div>
<section class="admin-detail-grid">
    <article class="admin-panel">
        <div class="panel-heading"><div><p>Customer and trip</p><h2><?= e($booking['product_title']) ?></h2></div><span class="<?= e(status_class($booking['payment_status'])) ?>">Payment: <?= e(ucfirst($booking['payment_status'])) ?></span></div>
        <dl class="record-list">
            <div><dt>Customer</dt><dd><?= e($booking['customer_name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($booking['customer_email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($booking['customer_phone']) ?></dd></div>
            <div><dt>Travel date</dt><dd><?= e(date('F j, Y', strtotime($booking['service_date']))) ?></dd></div>
            <div><dt>End date</dt><dd><?= $booking['end_date'] ? e(date('F j, Y', strtotime($booking['end_date']))) : 'Not applicable' ?></dd></div>
            <div><dt>Travelers / scope</dt><dd><?= e($booking['metrics']) ?></dd></div>
            <div><dt>Pickup / meeting</dt><dd><?= e($booking['pickup_address']) ?></dd></div>
            <div><dt>Destination</dt><dd><?= e($booking['destination_address']) ?></dd></div>
            <div><dt>Special requests</dt><dd><?= e($booking['special_requests'] ?: 'None') ?></dd></div>
        </dl>
    </article>
    <aside class="admin-panel">
        <div class="panel-heading"><div><p>Financial summary</p><h2><?= e(format_money($booking['total_amount'])) ?></h2></div></div>
        <dl class="record-list compact-list">
            <div><dt>Unit price</dt><dd><?= e(format_money($booking['unit_price'])) ?></dd></div>
            <div><dt>Subtotal</dt><dd><?= e(format_money($booking['subtotal_amount'])) ?></dd></div>
            <div><dt>Discount</dt><dd><?= e(format_money($booking['discount_amount'])) ?></dd></div>
            <div><dt>Payment</dt><dd><span class="<?= e(status_class($booking['payment_status'])) ?>"><?= e(ucfirst($booking['payment_status'])) ?></span></dd></div>
            <div><dt>Paid at</dt><dd><?= $booking['paid_at'] ? e(date('M j, Y g:i A', strtotime($booking['paid_at']))) : 'Not paid' ?></dd></div>
        </dl>
        <p class="immutable-note">Payment status cannot be edited here. Only a Khalti lookup can set it to paid.</p>
    </aside>
</section>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Staff control</p><h2>Operations status</h2></div></div>
    <form method="post" class="operation-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
        <label>Booking status<select name="booking_status"><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>" <?= $booking['booking_status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
        <label class="grow">Internal note<textarea name="admin_note" rows="3" maxlength="3000" placeholder="Visible only to staff"><?= e($booking['admin_note']) ?></textarea></label>
        <button class="btn btn-primary" type="submit">Save status</button>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Audit trail</p><h2>Khalti attempts</h2></div><a href="payments.php?booking=<?= (int) $booking['id'] ?>">Payment controls</a></div>
    <div class="table-wrap"><table><thead><tr><th>Attempt</th><th>PIDX</th><th>Transaction</th><th>Amount</th><th>Khalti status</th><th>Verified</th></tr></thead><tbody>
    <?php if (!$payments): ?><tr><td colspan="6" class="empty-cell">No Khalti attempt recorded.</td></tr><?php endif; ?>
    <?php foreach ($payments as $payment): ?><tr><td>#<?= (int) $payment['attempt_no'] ?><small><?= e(date('M j, g:i A', strtotime($payment['created_at']))) ?></small></td><td class="mono"><?= e($payment['pidx'] ?: 'Not initiated') ?></td><td class="mono"><?= e($payment['transaction_id'] ?: '-') ?></td><td><?= e(format_money(((int) $payment['amount_paisa']) / 100)) ?></td><td><span class="<?= e(status_class(provider_status_to_internal($payment['provider_status']))) ?>"><?= e($payment['provider_status']) ?></span></td><td><?= $payment['verified_at'] ? e(date('M j, g:i A', strtotime($payment['verified_at']))) : '-' ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
