<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!request_is_post()) {
    json_response(['success' => false, 'message' => 'POST requests only.'], 405);
}
verify_csrf();

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = filter_var(strtolower(trim((string) ($_POST['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

$errors = [];
if (strlen($fullName) < 2 || strlen($fullName) > 100) {
    $errors[] = 'Enter your full name.';
}
if (!$email) {
    $errors[] = 'Enter a valid email address.';
}
if ($phone !== '' && !valid_nepal_phone($phone)) {
    $errors[] = 'Enter a valid Nepal mobile number.';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must contain at least 8 characters.';
}
if ($password !== $passwordConfirmation) {
    $errors[] = 'Password confirmation does not match.';
}
if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    json_response(['success' => false, 'message' => 'An account already exists for this email address.'], 409);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')"
    );
    $stmt->execute([
        $fullName,
        $email,
        $phone === '' ? null : normalize_phone($phone),
        password_hash($password, PASSWORD_DEFAULT),
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['user_name'] = $fullName;
    $_SESSION['user_role'] = 'customer';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    json_response([
        'success' => true,
        'message' => 'Your account is ready.',
        'redirect' => app_url('index.php'),
    ], 201);
} catch (PDOException $exception) {
    error_log('Registration failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Account creation failed. Please try again.'], 500);
}
