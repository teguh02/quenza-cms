<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Packages\PackageDefinition;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Packages\PackageScope;
use RuntimeException;

final class SeederRunner
{
    public function __construct(
        private readonly Application $app,
        private readonly Connection $connection,
        private readonly PackageDiscoverer $discoverer,
    ) {
    }

    public function run(?PackageScope $scope = null, ?string $package = null, ?string $class = null): int
    {
        if ($class !== null) {
            $this->runSeeder($this->resolveSeederClass($class, $scope, $package));

            return 1;
        }

        $seeders = $this->defaultSeeders($scope, $package);

        foreach ($seeders as $seederClass) {
            $this->runSeeder($seederClass);
        }

        return count($seeders);
    }

    /**
     * @return list<string>
     */
    private function defaultSeeders(?PackageScope $scope = null, ?string $package = null): array
    {
        $seeders = [];

        if (($scope === null || $scope === PackageScope::Core) && ($package === null || $package === 'core')) {
            $seeders[] = 'Database\\Seeders\\DatabaseSeeder';
        }

        if ($scope === null || $scope === PackageScope::Plugin) {
            foreach ($this->discoverer->discoverPlugins() as $plugin) {
                if ($package !== null && $plugin->slug !== $package) {
                    continue;
                }

                $seeders = [...$seeders, ...$this->packageSeeders($plugin)];
            }
        }

        if ($scope === null || $scope === PackageScope::Theme) {
            foreach ($this->discoverer->discoverThemes(activeOnly: true) as $theme) {
                if ($package !== null && $theme->slug !== $package) {
                    continue;
                }

                $seeders = [...$seeders, ...$this->packageSeeders($theme)];
            }
        }

        return array_values(array_filter($seeders, 'class_exists'));
    }

    /**
     * @return list<string>
     */
    private function packageSeeders(PackageDefinition $package): array
    {
        if ($package->seederPath === null) {
            return [];
        }

        return [$package->namespace . 'Database\\Seeders\\DatabaseSeeder'];
    }

    private function resolveSeederClass(string $class, ?PackageScope $scope = null, ?string $package = null): string
    {
        if (str_contains($class, '\\')) {
            return $class;
        }

        if ($scope === null || $scope === PackageScope::Core) {
            return 'Database\\Seeders\\' . $class;
        }

        if ($package === null) {
            throw new RuntimeException('Seeder non-core wajib menyertakan opsi --package.');
        }

        foreach ($this->discoverer->discoverAll() as $candidate) {
            if ($candidate->scope === $scope && $candidate->slug === $package) {
                return $candidate->namespace . 'Database\\Seeders\\' . $class;
            }
        }

        throw new RuntimeException(sprintf('Package %s tidak ditemukan untuk scope %s.', $package, $scope->value));
    }

    private function runSeeder(string $className): void
    {
        if (!class_exists($className)) {
            throw new RuntimeException(sprintf('Class seeder tidak ditemukan: %s', $className));
        }

        if (!is_subclass_of($className, Seeder::class)) {
            throw new RuntimeException(sprintf('Class %s bukan turunan Seeder yang valid.', $className));
        }

        $instance = new $className($this->connection, $this->app);

        $this->connection->transaction(static function () use ($instance): void {
            $instance->run();
        });
    }
}
