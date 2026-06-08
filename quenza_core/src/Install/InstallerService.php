<?php
declare(strict_types=1);

namespace Quenza\Core\Install;

use Database\Seeders\InstallerSeeder;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Auth\RateLimiterService;
use Quenza\Core\Auth\RegistrationService;
use Quenza\Core\Cms\ActivityLogService;
use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\Connection;
use Quenza\Core\Database\DatabaseDriver;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\MigrationRepository;
use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;
use Quenza\Core\Database\Schema\SchemaManager;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Runtime\RuntimeEnvironment;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Translation\Translator;
use Quenza\Core\View\TwigRenderer;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class InstallerService
{
    public function __construct(
        private readonly Application $app,
        private readonly Security $security,
        private readonly SessionManager $session,
        private readonly InstallLockRepository $lock,
        private readonly InstallerConfigPrefill $prefill,
        private readonly RuntimeEnvironment $runtime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prefill(): array
    {
        return $this->prefill->defaults();
    }

    public function manualConfigurationDetected(): bool
    {
        return $this->runtime->hasManualEnvironmentFile();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{valid: bool, data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateDatabaseConfiguration(array $input): array
    {
        try {
            $driver = DatabaseDriver::fromName((string) ($input['driver'] ?? 'sqlite'));
        } catch (InvalidArgumentException $exception) {
            return [
                'valid' => false,
                'data' => [],
                'errors' => ['driver' => $exception->getMessage()],
            ];
        }

        $errors = [];
        $publicScheme = strtolower(trim((string) ($input['public_scheme'] ?? 'http')));
        $publicHostname = trim((string) ($input['public_hostname'] ?? 'localhost'));
        $appPublicPort = max(1, (int) ($input['app_public_port'] ?? 80));
        $appInternalPort = max(1, (int) ($input['app_internal_port'] ?? 80));
        $dbPublishedPort = max(1, (int) ($input['db_published_port'] ?? ($input['port'] ?? 3306)));

        $config = [
            'driver' => $driver->value,
            'charset' => 'utf8mb4',
            'prefix' => 'qz_',
            'options' => config('database.options', []),
            'public_scheme' => in_array($publicScheme, ['http', 'https'], true) ? $publicScheme : 'http',
            'public_hostname' => $publicHostname !== '' ? $publicHostname : 'localhost',
            'app_public_port' => $appPublicPort,
            'app_internal_port' => $appInternalPort,
            'db_published_port' => $dbPublishedPort,
            'runtime_context' => $this->runtime->context(),
        ];

        if ($driver === DatabaseDriver::Sqlite) {
            $sqlitePath = trim((string) ($input['sqlite_path'] ?? 'storage/database/quenza.db'));
            $config['sqlite_path'] = $this->normalizeSqlitePath($sqlitePath);
            $config['sqlite_path_display'] = $sqlitePath;
        } else {
            $host = trim((string) ($input['host'] ?? ''));
            $port = (int) ($input['port'] ?? 3306);
            $database = trim((string) ($input['database'] ?? ''));
            $username = trim((string) ($input['username'] ?? ''));
            $password = (string) ($input['password'] ?? '');

            if ($host === '') {
                $errors['host'] = 'Host database wajib diisi.';
            }

            if ($database === '') {
                $errors['database'] = 'Nama database wajib diisi.';
            }

            if ($username === '') {
                $errors['username'] = 'User database wajib diisi.';
            }

            $config += [
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
            ];
        }

        if ($errors !== []) {
            return ['valid' => false, 'data' => $config, 'errors' => $errors];
        }

        try {
            (new Connection($config))->pdo();
        } catch (Throwable $throwable) {
            return [
                'valid' => false,
                'data' => $config,
                'errors' => ['connection' => 'Koneksi database gagal: ' . $throwable->getMessage()],
            ];
        }

        return ['valid' => true, 'data' => $config, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{valid: bool, data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateSiteConfiguration(array $input, ?string $prefilledPassword = null): array
    {
        $siteTitle = trim((string) ($input['site_title'] ?? ''));
        $adminUsername = trim((string) ($input['admin_username'] ?? ''));
        $adminEmail = trim((string) ($input['admin_email'] ?? ''));
        $adminPassword = (string) ($input['admin_password'] ?? '');

        if ($adminPassword === '' && $prefilledPassword !== null) {
            $adminPassword = $prefilledPassword;
        }

        $errors = [];

        if ($siteTitle === '') {
            $errors['site_title'] = 'Judul situs wajib diisi.';
        }

        if ($adminUsername === '' || !preg_match('/^[A-Za-z0-9_\-\.]{3,40}$/', $adminUsername)) {
            $errors['admin_username'] = 'Username admin wajib 3-40 karakter dan hanya boleh huruf, angka, titik, garis bawah, atau dash.';
        }

        if ($this->security->sanitizeEmail($adminEmail) === null) {
            $errors['admin_email'] = 'Email admin tidak valid.';
        }

        if (!$this->isStrongPassword($adminPassword)) {
            $errors['admin_password'] = 'Password admin minimal 10 karakter dan harus mengandung huruf besar, huruf kecil, angka, dan simbol.';
        }

        return [
            'valid' => $errors === [],
            'data' => [
                'site_title' => $siteTitle,
                'admin_username' => $adminUsername,
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
                'has_admin_password_prefill' => $prefilledPassword !== null && $prefilledPassword !== '',
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $databaseConfig
     * @param array<string, mixed> $siteConfig
     */
    public function install(string $locale, array $databaseConfig, array $siteConfig, string $fallbackSiteUrl): void
    {
        if ($locale === '') {
            throw new InvalidArgumentException('Locale instalasi wajib diisi.');
        }

        $finalSiteUrl = $this->buildPublicUrl($databaseConfig, $fallbackSiteUrl);

        $this->writeEnvironmentFile($locale, $databaseConfig, $siteConfig, $finalSiteUrl);
        $this->rebindDatabaseServices($databaseConfig);

        /** @var Migrator $migrator */
        $migrator = $this->app->get(Migrator::class);
        $migrator->fresh();

        $connection = $this->app->get(Connection::class);
        $seeder = new InstallerSeeder($connection, $this->app, [
            'locale' => $locale,
            'site_title' => (string) $siteConfig['site_title'],
            'site_url' => $finalSiteUrl,
            'admin_username' => (string) $siteConfig['admin_username'],
            'admin_email' => (string) $siteConfig['admin_email'],
            'admin_password' => (string) $siteConfig['admin_password'],
        ]);

        $connection->transaction(static function () use ($seeder): void {
            $seeder->run();
        });

        $this->app->get(OptionService::class)->set('installation_completed_at', date('Y-m-d H:i:s'));

        $this->lock->write([
            'installed_at' => date('Y-m-d H:i:s'),
            'driver' => $databaseConfig['driver'],
            'site_url' => $finalSiteUrl,
            'locale' => $locale,
            'runtime' => $this->runtime->context(),
            'version' => '0.1.0',
        ]);

        $this->session->forget('installer');
    }

    /**
     * @param array<string, mixed> $databaseConfig
     * @param array<string, mixed> $siteConfig
     */
    private function writeEnvironmentFile(string $locale, array $databaseConfig, array $siteConfig, string $siteUrl): void
    {
        $lines = [
            'APP_NAME="' . str_replace('"', '\\"', (string) $siteConfig['site_title']) . '"',
            'APP_ENV=local',
            'APP_DEBUG=true',
            'APP_URL="' . str_replace('"', '\\"', $siteUrl) . '"',
            'QZ_RUNTIME=' . ($this->runtime->usesManualOverride() ? $this->runtime->context() : 'auto'),
            'APP_TIMEZONE="Asia/Jakarta"',
            'APP_LOCALE=' . $locale,
            'APP_FALLBACK_LOCALE=' . ($locale === 'id' ? 'en' : 'id'),
            'SESSION_NAME=QUENZASESSID',
            '',
            'DB_DRIVER=' . $databaseConfig['driver'],
            'DB_SQLITE_PATH=' . ($databaseConfig['sqlite_path_display'] ?? 'storage/database/quenza.db'),
            'DB_HOST=' . ($databaseConfig['host'] ?? '127.0.0.1'),
            'DB_PORT=' . ($databaseConfig['port'] ?? 3306),
            'DB_DATABASE=' . ($databaseConfig['database'] ?? 'quenza_cms'),
            'DB_USERNAME=' . ($databaseConfig['username'] ?? 'root'),
            'DB_PASSWORD=' . ($databaseConfig['password'] ?? ''),
            'DB_CHARSET=' . ($databaseConfig['charset'] ?? 'utf8mb4'),
            'DB_PREFIX=' . ($databaseConfig['prefix'] ?? 'qz_'),
            'QZ_PUBLIC_SCHEME=' . ($databaseConfig['public_scheme'] ?? 'http'),
            'QZ_PUBLIC_HOST=' . ($databaseConfig['public_hostname'] ?? 'localhost'),
            'QZ_DOCKER_APP_PUBLIC_PORT=' . ($databaseConfig['app_public_port'] ?? 80),
            'QZ_DOCKER_APP_INTERNAL_PORT=' . ($databaseConfig['app_internal_port'] ?? 80),
            'QZ_DOCKER_DB_PUBLISHED_PORT=' . ($databaseConfig['db_published_port'] ?? ($databaseConfig['port'] ?? 3306)),
            '',
            'QZ_ACTIVE_THEME=quenza_default',
            'QZ_ADMIN_NAME=',
            'QZ_ADMIN_USERNAME=' . ($siteConfig['admin_username'] ?? ''),
            'QZ_ADMIN_EMAIL=' . ($siteConfig['admin_email'] ?? ''),
            'QZ_ADMIN_PASSWORD=',
        ];

        $result = file_put_contents($this->app->basePath('.env'), implode(PHP_EOL, $lines) . PHP_EOL);

        if ($result === false) {
            throw new RuntimeException('Gagal menulis file .env hasil instalasi.');
        }
    }

    /**
     * @param array<string, mixed> $databaseConfig
     */
    private function rebindDatabaseServices(array $databaseConfig): void
    {
        $app = $this->app;

        $app->singleton(Connection::class, static fn (Application $application): Connection => new Connection($databaseConfig));
        $app->singleton(DatabaseManager::class, static fn (Application $application): DatabaseManager => new DatabaseManager($application->get(Connection::class)));
        $app->singleton(SchemaManager::class, static fn (Application $application): SchemaManager => new SchemaManager($application->get(Connection::class)));
        $app->singleton(MigrationRepository::class, static fn (Application $application): MigrationRepository => new MigrationRepository(
            $application->get(Connection::class),
            $application->get(DatabaseManager::class),
            $application->get(SchemaManager::class),
        ));
        $app->singleton(Migrator::class, static fn (Application $application): Migrator => new Migrator(
            $application,
            $application->get(Connection::class),
            $application->get(MigrationRepository::class),
            $application->get(PackageDiscoverer::class),
        ));
        $app->singleton(SeederRunner::class, static fn (Application $application): SeederRunner => new SeederRunner(
            $application,
            $application->get(Connection::class),
            $application->get(PackageDiscoverer::class),
        ));
        $app->singleton(OptionService::class, static fn (Application $application): OptionService => new OptionService($application->get(DatabaseManager::class)));
        $app->singleton(ActivityLogService::class, static fn (Application $application): ActivityLogService => new ActivityLogService($application->get(DatabaseManager::class)));
        $app->singleton(RateLimiterService::class, static fn (Application $application): RateLimiterService => new RateLimiterService($application->get(DatabaseManager::class)));
        $app->singleton(AuthManager::class, static fn (Application $application): AuthManager => new AuthManager(
            $application->get(DatabaseManager::class),
            $application->get(SessionManager::class),
            $application->get(Security::class),
            $application->get(RateLimiterService::class),
        ));
        $app->singleton(RegistrationService::class, static fn (Application $application): RegistrationService => new RegistrationService(
            $application->get(DatabaseManager::class),
            $application->get(Security::class),
            $application->get(AuthManager::class),
            $application->get(RateLimiterService::class),
        ));
        $app->singleton(TwigRenderer::class, static fn (Application $application): TwigRenderer => new TwigRenderer(
            $application,
            $application->get(Security::class),
            $application->get(SessionManager::class),
            $application->get(AuthManager::class),
            $application->get(Translator::class),
        ));
    }

    private function buildPublicUrl(array $databaseConfig, string $fallbackBaseUrl): string
    {
        $scheme = strtolower(trim((string) ($databaseConfig['public_scheme'] ?? 'http')));
        $host = trim((string) ($databaseConfig['public_hostname'] ?? 'localhost'));
        $port = (int) ($databaseConfig['app_public_port'] ?? 80);

        if ($host === '') {
            return $fallbackBaseUrl;
        }

        $url = $scheme . '://' . $host;

        if (!(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $url .= ':' . $port;
        }

        return $url;
    }

    private function normalizeSqlitePath(string $path): string
    {
        if ($path === '') {
            return $this->app->basePath('storage/database/quenza.db');
        }

        if (preg_match('/^[A-Za-z]:\\\\|^\/|^\\\\/', $path) === 1) {
            return $path;
        }

        return $this->app->basePath(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}
