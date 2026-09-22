<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    json_response(['success' => false, 'message' => 'POST requests only.'], 405);
}
verify_csrf();

$attempts = (int) ($_SESSION['login_attempts'] ?? 0);
$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
if ($lockedUntil > time()) {
    json_response(['success' => false, 'message' => 'Too many login attempts. Wait a few minutes and try again.'], 429);
}

$email = filter_var(strtolower(trim((string) ($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
$password = (string) ($_POST['password'] ?? '');
if (!$email || $password === '') {
    json_response(['success' => false, 'message' => 'Enter a valid email address and password.'], 422);
}

$stmt = $pdo->prepare('SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
    $attempts++;
    $_SESSION['login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['login_locked_until'] = time() + 300;
        $_SESSION['login_attempts'] = 0;
    }
    json_response(['success' => false, 'message' => 'The email or password is incorrect.'], 401);
}

session_regenerate_id(true);
unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = (string) $user['full_name'];
$_SESSION['user_role'] = (string) $user['role'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

json_response([
    'success' => true,
    'message' => 'Welcome back, ' . $user['full_name'] . '!',
    'redirect' => $user['role'] === 'admin' ? app_url('admin/index.php') : app_url('index.php'),
]);
