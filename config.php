<?php
declare(strict_types=1);

function loadEnv(string $file): void {
    if (!is_file($file)) {
        throw new RuntimeException(".env file not found. Copy .env.example to .env.");
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

loadEnv(__DIR__ . '/.env');

function env(string $key, ?string $default = null): string {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? ($default ?? '') : (string)$value;
}

date_default_timezone_set('Africa/Accra');

return [
    'app' => [
        'env' => env('APP_ENV', 'sandbox'),
        'url' => rtrim(env('APP_URL', ''), '/'),
        'secret' => env('APP_SECRET'),
        'admin_user' => env('APP_ADMIN_USER', 'admin'),
        'admin_password_hash' => env('APP_ADMIN_PASSWORD_HASH'),
    ],
    'db' => [
        'dsn' => sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'mtn_momo')
        ),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],
    'momo' => [
        'base_url' => rtrim(env('MOMO_BASE_URL'), '/'),
        'target_environment' => env('MOMO_TARGET_ENVIRONMENT', 'sandbox'),
        'subscription_key' => env('MOMO_SUBSCRIPTION_KEY'),
        'api_user' => env('MOMO_API_USER'),
        'api_key' => env('MOMO_API_KEY'),
        'callback_url' => env('MOMO_CALLBACK_URL'),
        'currency' => env('MOMO_CURRENCY', 'GHS'),
        'country_code' => env('MOMO_COUNTRY_CODE', '233'),
        'party_id_type' => env('MOMO_PARTY_ID_TYPE', 'MSISDN'),
    ],
];
