<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$errors = [];
$created = false;

if (request_is_post() && $adminCount === 0) {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $email = filter_var(strtolower(trim((string) ($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirmation'] ?? '');

    if (strlen($name) < 2 || strlen($name) > 100) $errors[] = 'Enter the administrator name.';
    if (!$email) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !valid_nepal_phone($phone)) $errors[] = 'Enter a valid Nepal mobile number.';
    if (strlen($password) < 10) $errors[] = 'Use an administrator password with at least 10 characters.';
    if ($password !== $confirm) $errors[] = 'Password confirmation does not match.';

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'That email already belongs to a customer account. Use a different address or promote it directly in the database.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, $phone === '' ? null : normalize_phone($phone), password_hash($password, PASSWORD_DEFAULT)]);
            $created = true;
            $adminCount = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Administrator Setup | Gantavya</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-bg">
<main class="result-wrap">
    <section class="result-card">
        <p class="eyebrow">One-time setup</p>
        <h1>Gantavya administrator</h1>
        <?php if ($created): ?>
            <div class="alert alert-success">Administrator created. This setup page is now locked.</div>
            <div class="button-row center"><a class="btn btn-primary" href="admin/login.php">Open admin login</a></div>
        <?php elseif ($adminCount > 0): ?>
            <p>An administrator already exists, so this page cannot create another one.</p>
            <div class="button-row center"><a class="btn btn-primary" href="admin/login.php">Open admin login</a><a class="btn btn-light" href="index.php">Home</a></div>
        <?php else: ?>
            <p>Create the first administrator after importing <code>schema.sql</code>. No default password is stored in the project.</p>
            <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <form method="post" style="text-align:left; margin-top:22px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>Full name<input type="text" name="full_name" maxlength="100" required></label>
                <label>Email address<input type="email" name="email" required></label>
                <label>Phone number <small>(optional)</small><input type="tel" name="phone" maxlength="20"></label>
                <label>Password<input type="password" name="password" minlength="10" required></label>
                <label>Confirm password<input type="password" name="password_confirmation" minlength="10" required></label>
                <button class="btn btn-primary full" type="submit">Create first administrator</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
