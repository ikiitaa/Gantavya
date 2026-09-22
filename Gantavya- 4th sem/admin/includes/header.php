<?php
declare(strict_types=1);

if (!isset($pdo, $config)) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}
$adminUser = $adminUser ?? require_admin();
$pageTitle = $pageTitle ?? 'Admin';
$currentFile = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$flashes = pull_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Gantavya Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
<aside class="admin-sidebar">
    <a class="brand inverse" href="index.php">Gantavya<span>.</span></a>
    <p class="admin-label">Administration</p>
    <nav>
        <a class="<?= $currentFile === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
        <a class="<?= in_array($currentFile, ['bookings.php', 'booking.php'], true) ? 'active' : '' ?>" href="bookings.php">Bookings</a>
        <a class="<?= $currentFile === 'payments.php' ? 'active' : '' ?>" href="payments.php">Payments</a>
        <a class="<?= $currentFile === 'products.php' ? 'active' : '' ?>" href="products.php">Products</a>
        <a class="<?= $currentFile === 'users.php' ? 'active' : '' ?>" href="users.php">Users</a>
        
    </nav>
    <div class="admin-account">
        <strong><?= e($adminUser['full_name']) ?></strong>
        <small><?= e($adminUser['email']) ?></small>
        <form action="../api/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">Logout</button>
        </form>
    </div>
</aside>
<main class="admin-main">
    <header class="admin-topbar">
        <button class="admin-menu-toggle" type="button">Menu</button>
        <div><p>Gantavya EMIS</p><h1><?= e($pageTitle) ?></h1></div>
        <span><?= e(date('D, M j, Y')) ?></span>
    </header>
    <?php if (empty($config['khalti']['secret_key'])): ?>
        <div class="alert alert-warning">Khalti is not configured. Add the server-side KHALTI_SECRET_KEY to .env before accepting bookings.</div>
    <?php endif; ?>
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
