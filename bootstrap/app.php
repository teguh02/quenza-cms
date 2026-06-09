<?php
declare(strict_types=1);

use Quenza\Core\Console\CommandKernel;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Auth\RateLimiterService;
use Quenza\Core\Auth\RegistrationService;
use Quenza\Core\Cms\ActivityLogService;
use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\Connection;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\MigrationRepository;
use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;
use Quenza\Core\Database\Schema\SchemaManager;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Foundation\Autoloader;
use Quenza\Core\Http\HttpKernel;
use Quenza\Core\Http\Router;
use Quenza\Core\Install\InstallLockRepository;
use Quenza\Core\Install\InstallerConfigPrefill;
use Quenza\Core\Install\InstallationState;
use Quenza\Core\Install\InstallerService;
use Quenza\Core\Packages\ManifestValidator;
use Quenza\Core\Packages\PackageDefinition;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Support\Env;
use Quenza\Core\Translation\Translator;
use Quenza\Core\View\TwigRenderer;
use Quenza\Core\Runtime\RuntimeEnvironment;

$basePath = dirname(__DIR__);

$vendorAutoload = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$useComposerAutoload = is_file($vendorAutoload);

require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'Env.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Application.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'helpers.php';

Env::load(
    $basePath . DIRECTORY_SEPARATOR . '.env',
    overrideExisting: (string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')) !== 'testing',
);

$autoloader = new Autoloader();
$autoloader->addNamespace('Quenza\\Core\\', $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src');
$autoloader->addNamespace('Database\\Migrations\\', $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
$autoloader->addNamespace('Database\\Seeders\\', $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders');

if ($useComposerAutoload) {
    // === DEVELOPER MODE: vendor/ composer tersedia ===
    require_once $vendorAutoload;
} else {
    // === STANDALONE MODE (Zero-Composer): Gunakan libs/ yang sudah di-bundle ===
    $autoloader->addNamespace('Twig\\', $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'twig' . DIRECTORY_SEPARATOR . 'src');

    $htmlPurifierAutoload = $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'htmlpurifier' . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'HTMLPurifier.auto.php';
    if (is_file($htmlPurifierAutoload)) {
        require_once $htmlPurifierAutoload;
    }
}

$autoloader->register();

$config = [
    'app' => require $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
    'database' => require $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
];

$app = new Application($basePath, $autoloader, $config);
Application::setInstance($app);

$app->singleton(Connection::class, static fn (Application $application): Connection => new Connection(
    (array) $application->config('database', []),
));

$app->singleton(DatabaseManager::class, static fn (Application $application): DatabaseManager => new DatabaseManager(
    $application->get(Connection::class),
));

$app->singleton(SchemaManager::class, static fn (Application $application): SchemaManager => new SchemaManager(
    $application->get(Connection::class),
));

$app->singleton(OptionService::class, static fn (Application $application): OptionService => new OptionService(
    $application->get(DatabaseManager::class),
));

$app->singleton(ActivityLogService::class, static fn (Application $application): ActivityLogService => new ActivityLogService(
    $application->get(DatabaseManager::class),
));

$app->singleton(Security::class, static fn (Application $application): Security => new Security($application));

$app->singleton(SessionManager::class, static fn (Application $application): SessionManager => new SessionManager($application));

$app->singleton(RuntimeEnvironment::class, static fn (Application $application): RuntimeEnvironment => new RuntimeEnvironment($application));

$app->singleton(Translator::class, static fn (Application $application): Translator => new Translator(
    $application->basePath('quenza_core/lang'),
    (string) $application->config('app.locale', 'id'),
    (string) $application->config('app.fallback_locale', 'en'),
));

$app->singleton(ManifestValidator::class, static fn (Application $application): ManifestValidator => new ManifestValidator());

$app->singleton(PackageDiscoverer::class, static fn (Application $application): PackageDiscoverer => new PackageDiscoverer(
    $application->basePath(),
    $application->get(ManifestValidator::class),
    (string) $application->config('app.active_theme', 'default'),
));

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

$app->singleton(RateLimiterService::class, static fn (Application $application): RateLimiterService => new RateLimiterService(
    $application->get(DatabaseManager::class),
));

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

$app->singleton(InstallationState::class, static fn (Application $application): InstallationState => new InstallationState(
    $application,
    $application->get(Connection::class),
    $application->get(OptionService::class),
    $application->get(SessionManager::class),
    $application->get(InstallLockRepository::class),
));

$app->singleton(InstallLockRepository::class, static fn (Application $application): InstallLockRepository => new InstallLockRepository($application));

$app->singleton(InstallerConfigPrefill::class, static fn (Application $application): InstallerConfigPrefill => new InstallerConfigPrefill(
    $application,
    $application->get(RuntimeEnvironment::class),
));

$app->singleton(InstallerService::class, static fn (Application $application): InstallerService => new InstallerService(
    $application,
    $application->get(Security::class),
    $application->get(SessionManager::class),
    $application->get(InstallLockRepository::class),
    $application->get(InstallerConfigPrefill::class),
    $application->get(RuntimeEnvironment::class),
));

$app->singleton(TwigRenderer::class, static fn (Application $application): TwigRenderer => new TwigRenderer(
    $application,
    $application->get(Security::class),
    $application->get(SessionManager::class),
    $application->get(AuthManager::class),
    $application->get(Translator::class),
));

$app->singleton(Router::class, static fn (Application $application): Router => new Router());

$app->singleton(HttpKernel::class, static fn (Application $application): HttpKernel => new HttpKernel(
    $application,
    $application->get(Router::class),
    $application->get(SessionManager::class),
));

$app->singleton(CommandKernel::class, static fn (Application $application): CommandKernel => new CommandKernel($application));

$registerPackageNamespaces = static function (Autoloader $loader, PackageDefinition $package): void {
    $loader->addNamespace($package->namespace, $package->sourcePath);

    if ($package->migrationPath !== null) {
        $loader->addNamespace($package->namespace . 'Database\\Migrations\\', $package->migrationPath);
    }

    if ($package->seederPath !== null) {
        $loader->addNamespace($package->namespace . 'Database\\Seeders\\', $package->seederPath);
    }
};

foreach ($app->get(PackageDiscoverer::class)->discoverPlugins() as $plugin) {
    $registerPackageNamespaces($autoloader, $plugin);
}

foreach ($app->get(PackageDiscoverer::class)->discoverThemes(activeOnly: true) as $theme) {
    $registerPackageNamespaces($autoloader, $theme);
}

date_default_timezone_set((string) $app->config('app.timezone', 'UTC'));

return $app;
