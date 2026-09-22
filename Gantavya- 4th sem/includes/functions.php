<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    global $config;
    return rtrim($config['url'], '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect(string $path, int $status = 302): void
{
    $location = preg_match('#^https?://#i', $path) ? $path : app_url($path);
    header('Location: ' . $location, true, $status);
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token = null): void
{
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            json_response(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page, and try again.');
    }
}

function current_user(): ?array
{
    global $pdo;
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role, is_active, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $found = $stmt->fetch();

    if (!$found || (int) $found['is_active'] !== 1) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
        return null;
    }

    $user = $found;
    return $user;
}

function require_login(bool $json = false): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }

    if ($json) {
        json_response(['success' => false, 'message' => 'Please log in before continuing.'], 401);
    }

    flash('error', 'Please log in before continuing.');
    redirect('index.php');
}

function require_admin(): array
{
    $user = require_login(false);
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Administrator access is required.');
    }
    return $user;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function random_code(string $prefix = 'GAN'): string
{
    return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function valid_nepal_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return is_string($digits) && preg_match('/^(?:977)?9[678]\d{8}$/', $digits) === 1;
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (strlen($digits) === 13 && strncmp($digits, '977', 3) === 0) {
        return substr($digits, 3);
    }
    return $digits;
}

function format_money($amount): string
{
    return 'NPR ' . number_format((float) $amount, 2);
}

function status_class(string $status): string
{
    $status = strtolower(str_replace(' ', '_', $status));
    if (in_array($status, ['paid', 'completed', 'confirmed', 'active'], true)) {
        return 'status status-success';
    }
    if (in_array($status, ['failed', 'cancelled', 'expired', 'refunded', 'inactive', 'user_canceled'], true)) {
        return 'status status-danger';
    }
    if (in_array($status, ['initiated', 'pending', 'unpaid', 'in_progress'], true)) {
        return 'status status-warning';
    }
    return 'status';
}

function provider_status_to_internal(string $providerStatus): string
{
    $normalized = strtolower(trim($providerStatus));
    if ($normalized === 'completed') {
        return 'paid';
    }
    if (in_array($normalized, ['pending', 'initiated'], true)) {
        return 'pending';
    }
    if (in_array($normalized, ['refunded', 'partially refunded'], true)) {
        return 'refunded';
    }
    return 'failed';
}
