<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$pageTitle = 'Users';

if (request_is_post()) {
    verify_csrf();
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$userId || $userId === (int) $adminUser['id']) {
        flash('error', 'You cannot change this account.');
    } else {
        $stmt = $pdo->prepare('UPDATE users SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'User access updated.' : 'User not found.');
    }
    redirect('admin/users.php', 303);
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $needle = '%' . $search . '%';
    $stmt = $pdo->prepare('SELECT u.*, COUNT(b.id) AS booking_count, COALESCE(SUM(CASE WHEN b.payment_status = \'paid\' THEN b.total_amount ELSE 0 END), 0) AS paid_total FROM users u LEFT JOIN bookings b ON b.user_id = u.id WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? GROUP BY u.id ORDER BY u.created_at DESC LIMIT 250');
    $stmt->execute([$needle, $needle, $needle]);
} else {
    $stmt = $pdo->query('SELECT u.*, COUNT(b.id) AS booking_count, COALESCE(SUM(CASE WHEN b.payment_status = \'paid\' THEN b.total_amount ELSE 0 END), 0) AS paid_total FROM users u LEFT JOIN bookings b ON b.user_id = u.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 250');
}
$users = $stmt->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel filter-panel"><form method="get" class="filter-form"><label>Search users<input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, phone"></label><button class="btn btn-primary" type="submit">Search</button><a class="btn btn-light" href="users.php">Reset</a></form></section>
<section class="admin-panel"><div class="panel-heading"><div><p>Accounts</p><h2><?= count($users) ?> user(s)</h2></div></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Bookings</th><th>Verified spend</th><th>Access</th><th></th></tr></thead><tbody>
<?php foreach ($users as $item): ?><tr><td><strong><?= e($item['full_name']) ?></strong><small>Joined <?= e(date('M j, Y', strtotime($item['created_at']))) ?></small></td><td><?= e($item['email']) ?><small><?= e($item['phone'] ?: 'No phone') ?></small></td><td><?= e(ucfirst($item['role'])) ?></td><td><?= (int) $item['booking_count'] ?></td><td><?= e(format_money($item['paid_total'])) ?></td><td><span class="<?= e(status_class((int) $item['is_active'] === 1 ? 'active' : 'inactive')) ?>"><?= (int) $item['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><?php if ((int) $item['id'] !== (int) $adminUser['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>"><button class="table-button" type="submit"><?= (int) $item['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
