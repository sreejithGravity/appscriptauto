<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use App\Database;
use App\Logger;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set('UTC');

// Simple helpers
function env(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?? $default;
}

$db = new Database(
    dsn: env('DB_DSN'),
    user: env('DB_USER'),
    pass: env('DB_PASS')
);

$logger = Logger::make(__DIR__ . '/../storage/logs/app.log');
