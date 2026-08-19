<?php
declare(strict_types=1);

function appConfig(): array {
    static $config;
    if (!$config) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $config = appConfig();
        $database = new Database($config['db']);
        $pdo = $database->pdo();
    }
    return $pdo;
}

function momo(): MomoClient {
    static $client;
    if (!$client) {
        $client = new MomoClient(appConfig()['momo']);
    }
    return $client;
}

function csrfToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function normalizeMsisdn(string $input): string {
    $digits = preg_replace('/\D+/', '', $input) ?? '';

    if (str_starts_with($digits, '233')) {
        $local = substr($digits, 3);
    } elseif (str_starts_with($digits, '0')) {
        $local = substr($digits, 1);
    } else {
        $local = $digits;
    }

    if (!preg_match('/^[2-5][0-9]{8}$/', $local)) {
        throw new InvalidArgumentException('Enter a valid Ghana mobile number, e.g. 0241234567.');
    }

    return '233' . $local;
}

function newUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function mapMomoStatus(?string $status): string {
    return match (strtoupper((string)$status)) {
        'SUCCESSFUL' => 'SUCCESSFUL',
        'FAILED' => 'FAILED',
        'PENDING' => 'PENDING',
        default => 'UNKNOWN',
    };
}
