<?php
declare(strict_types=1);

/**
 * Lightweight environment loader. Values in .env are never committed.
 */
function load_environment(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strncmp($line, '#', 1) === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

load_environment(dirname(__DIR__) . '/.env');

function env_value(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['true', '(true)'], true)) {
        return true;
    }
    if (in_array($normalized, ['false', '(false)'], true)) {
        return false;
    }
    if (in_array($normalized, ['null', '(null)'], true)) {
        return null;
    }

    return $value;
}

$appUrl = rtrim((string) env_value('APP_URL', 'http://localhost/proj4'), '/');
$khaltiMode = strtolower((string) env_value('KHALTI_MODE', 'sandbox'));
$khaltiBase = $khaltiMode === 'production'
    ? 'https://khalti.com/api/v2'
    : 'https://dev.khalti.com/api/v2';

return [
    'name' => (string) env_value('APP_NAME', 'Gantavya Travel & Tours'),
    'env' => (string) env_value('APP_ENV', 'local'),
    'debug' => (bool) env_value('APP_DEBUG', true),
    'url' => $appUrl,
    'timezone' => (string) env_value('APP_TIMEZONE', 'Asia/Kathmandu'),
    'db' => [
        'host' => (string) env_value('DB_HOST', '127.0.0.1'),
        'port' => (string) env_value('DB_PORT', '3306'),
        'name' => (string) env_value('DB_DATABASE', 'gantavya_db'),
        'username' => (string) env_value('DB_USERNAME', 'root'),
        'password' => (string) env_value('DB_PASSWORD', ''),
    ],
    'khalti' => [
        'mode' => $khaltiMode,
        'secret_key' => trim((string) env_value('KHALTI_SECRET_KEY', '')),
        'initiate_url' => $khaltiBase . '/epayment/initiate/',
        'lookup_url' => $khaltiBase . '/epayment/lookup/',
    ],
];
