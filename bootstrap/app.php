<?php
declare(strict_types=1);

use Quenza\Core\Console\CommandKernel;
use Quenza\Core\Database\Connection;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\MigrationRepository;
use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;
use Quenza\Core\Database\Schema\SchemaManager;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Foundation\Autoloader;
use Quenza\Core\Packages\ManifestValidator;
use Quenza\Core\Packages\PackageDefinition;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Security\Security;
use Quenza\Core\Support\Env;
use Quenza\Core\Translation\Translator;

$basePath = dirname(__DIR__);

$vendorAutoload = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'Env.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Application.php';
require_once $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'helpers.php';

Env::load($basePath . DIRECTORY_SEPARATOR . '.env');

$autoloader = new Autoloader();
$autoloader->addNamespace('Quenza\\Core\\', $basePath . DIRECTORY_SEPARATOR . 'quenza_core' . DIRECTORY_SEPARATOR . 'src');
$autoloader->addNamespace('Database\\Migrations\\', $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
$autoloader->addNamespace('Database\\Seeders\\', $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders');
$autoloader->register();

$config = [
    'app' => require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
    'database' => require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
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

$app->singleton(Security::class, static fn (Application $application): Security => new Security($application));

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
