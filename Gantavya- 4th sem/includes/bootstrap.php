<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/booking_calculator.php';
require_once __DIR__ . '/khalti.php';
require_once __DIR__ . '/payment_service.php';

date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('gantavya_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = time();
}
