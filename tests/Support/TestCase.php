<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\DatabaseDriver;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Http\HttpKernel;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;

abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;

    private ?string $sqliteDatabasePath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetState();
        $this->applyDefaultEnvironment();
        $this->app = $this->createApplication();
    }

    protected function tearDown(): void
    {
        $databasePath = $this->sqliteDatabasePath;
        unset($this->app);
        gc_collect_cycles();

        if ($databasePath !== null && is_file($databasePath)) {
            @unlink($databasePath);
        }

        $this->resetState();
        $this->sqliteDatabasePath = null;
        parent::tearDown();
    }

    protected function createApplication(): Application
    {
        /** @var Application $app */
        $app = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        return $app;
    }

    protected function database(): DatabaseManager
    {
        return $this->app->get(DatabaseManager::class);
    }

    protected function kernel(): HttpKernel
    {
        return $this->app->get(HttpKernel::class);
    }

    protected function security(): Security
    {
        return $this->app->get(Security::class);
    }

    protected function session(): SessionManager
    {
        return $this->app->get(SessionManager::class);
    }

    protected function driver(): DatabaseDriver
    {
        return $this->database()->driver();
    }

    protected function setEnvironment(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv(sprintf('%s=%s', $key, $value));
    }

    private function applyDefaultEnvironment(): void
    {
        $defaults = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'Asia/Jakarta',
            'APP_LOCALE' => 'id',
            'APP_FALLBACK_LOCALE' => 'en',
            'SESSION_NAME' => 'QUENZATESTSESSID',
            'DB_DRIVER' => 'sqlite',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'quenza_test',
            'DB_USERNAME' => 'quenza',
            'DB_PASSWORD' => 'quenza_secret',
            'DB_CHARSET' => 'utf8mb4',
            'DB_PREFIX' => 'qz_',
            'QZ_ACTIVE_THEME' => 'default',
            'QZ_ADMIN_NAME' => '',
            'QZ_ADMIN_EMAIL' => '',
            'QZ_ADMIN_PASSWORD' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
                continue;
            }

            $this->setEnvironment($key, $value);
        }

        $driver = strtolower((string) (getenv('DB_DRIVER') !== false ? getenv('DB_DRIVER') : ($_ENV['DB_DRIVER'] ?? 'sqlite')));

        if ($driver === 'sqlite') {
            $this->setEnvironment('DB_SQLITE_PATH', $this->sqliteDatabasePath());
        }
    }

    private function resetState(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_REQUEST = [];
        $_SESSION = [];

        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR', 'HTTP_HOST'] as $key) {
            unset($_SERVER[$key]);
        }
    }

    private function sqliteDatabasePath(): string
    {
        if ($this->sqliteDatabasePath !== null) {
            return $this->sqliteDatabasePath;
        }

        $testIdentifier = preg_replace('/[^A-Za-z0-9_]+/', '_', static::class . '_' . $this->name()) ?? 'quenza_phpunit';
        $this->sqliteDatabasePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . $testIdentifier . '.sqlite';

        return $this->sqliteDatabasePath;
    }
}
