<?php
declare(strict_types=1);

use Quenza\Core\Support\Env;

$sqlitePath = Env::string('DB_SQLITE_PATH', 'storage/database/quenza.db');

if (preg_match('/^[A-Za-z]:\\\\|^\/|^\\\\/', $sqlitePath) !== 1) {
    $sqlitePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sqlitePath);
}

return [
    'driver' => Env::string('DB_DRIVER', 'sqlite'),
    'host' => Env::string('DB_HOST', '127.0.0.1'),
    'port' => Env::int('DB_PORT', 3306),
    'database' => Env::string('DB_DATABASE', 'quenza_cms'),
    'sqlite_path' => $sqlitePath,
    'username' => Env::string('DB_USERNAME', 'root'),
    'password' => Env::string('DB_PASSWORD', ''),
    'charset' => Env::string('DB_CHARSET', 'utf8mb4'),
    'prefix' => Env::string('DB_PREFIX', 'qz_'),
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
];
