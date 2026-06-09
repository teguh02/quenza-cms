<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use FilesystemIterator;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Packages\PackageDefinition;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Packages\PackageScope;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly Application $app,
        private readonly Connection $connection,
        private readonly MigrationRepository $repository,
        private readonly PackageDiscoverer $discoverer,
    ) {
    }

    public function migrate(?PackageScope $scope = null, ?string $package = null): int
    {
        $migrations = $this->discoverMigrations($scope, $package);
        $executed = $this->repository->executedIndex($scope, $package);
        $pending = array_values(array_filter(
            $migrations,
            fn (MigrationDescriptor $migration): bool => !array_key_exists($migration->key(), $executed),
        ));

        if ($pending === []) {
            return 0;
        }

        $batch = $this->repository->nextBatchNumber();
        $count = 0;

        foreach ($migrations as $migration) {
            $record = $executed[$migration->key()] ?? null;

            if (is_array($record)) {
                if ((string) $record['checksum'] !== $migration->checksum) {
                    throw new RuntimeException(sprintf(
                        'Checksum migration berubah setelah dijalankan: %s',
                        $migration->className,
                    ));
                }

                continue;
            }

            $instance = $this->instantiateMigration($migration->className);
            $instance->up();
            $this->repository->log($migration, $batch);
            $count++;
        }

        return $count;
    }

    public function rollback(int $steps = 1, ?PackageScope $scope = null, ?string $package = null): int
    {
        $rows = $this->repository->rollbackCandidates($steps, $scope, $package);

        if ($rows === []) {
            return 0;
        }

        $count = 0;

        foreach ($rows as $row) {
            $className = (string) $row['migration'];
            $instance = $this->instantiateMigration($className);
            $instance->down();
            $this->repository->remove(PackageScope::from((string) $row['scope']), (string) $row['package'], $className);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array<string, string|int>>
     */
    public function status(?PackageScope $scope = null, ?string $package = null): array
    {
        $migrations = $this->discoverMigrations($scope, $package);
        $executed = $this->repository->executedIndex($scope, $package);
        $statusRows = [];

        foreach ($migrations as $migration) {
            $record = $executed[$migration->key()] ?? null;

            $state = match (true) {
                !is_array($record) => 'Pending',
                (string) $record['checksum'] !== $migration->checksum => 'Modified',
                default => 'Ran',
            };

            $statusRows[] = [
                'scope' => $migration->scope->value,
                'package' => $migration->package,
                'migration' => $migration->className,
                'status' => $state,
                'batch' => is_array($record) ? (int) $record['batch'] : 0,
            ];
        }

        return $statusRows;
    }

    public function fresh(): int
    {
        $pdo = $this->connection->pdo();
        $tables = $this->connection->listTablesByPrefix();

        if ($tables !== []) {
            $this->connection->setForeignKeyChecks(false);

            foreach ($tables as $table) {
                if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                    continue;
                }

                $pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $this->connection->quoteIdentifier($table)));
            }

            $this->connection->setForeignKeyChecks(true);
        }

        return $this->migrate();
    }

    /**
     * @return list<MigrationDescriptor>
     */
    private function discoverMigrations(?PackageScope $scope = null, ?string $package = null): array
    {
        $migrations = [];

        if ($scope === null || $scope === PackageScope::Core) {
            if ($package === null || $package === 'core') {
                $migrations = [
                    ...$migrations,
                    ...$this->scanDirectory(
                        PackageScope::Core,
                        'core',
                        'Database\\Migrations\\',
                        $this->app->basePath('quenza_core/database/migrations'),
                    ),
                ];
            }
        }

        if ($scope === null || $scope === PackageScope::Plugin) {
            foreach ($this->discoverer->discoverPlugins() as $plugin) {
                if ($package !== null && $plugin->slug !== $package) {
                    continue;
                }

                $migrations = [
                    ...$migrations,
                    ...$this->scanPackageMigrations($plugin),
                ];
            }
        }

        if ($scope === null || $scope === PackageScope::Theme) {
            foreach ($this->discoverer->discoverThemes(activeOnly: true) as $theme) {
                if ($package !== null && $theme->slug !== $package) {
                    continue;
                }

                $migrations = [
                    ...$migrations,
                    ...$this->scanPackageMigrations($theme),
                ];
            }
        }

        usort(
            $migrations,
            static fn (MigrationDescriptor $left, MigrationDescriptor $right): int => $left->className <=> $right->className,
        );

        return $migrations;
    }

    /**
     * @return list<MigrationDescriptor>
     */
    private function scanPackageMigrations(PackageDefinition $package): array
    {
        if ($package->migrationPath === null) {
            return [];
        }

        return $this->scanDirectory(
            $package->scope,
            $package->slug,
            $package->namespace . 'Database\\Migrations\\',
            $package->migrationPath,
        );
    }

    /**
     * @return list<MigrationDescriptor>
     */
    private function scanDirectory(PackageScope $scope, string $package, string $namespacePrefix, string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $migrations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            $className = $namespacePrefix . $this->classSuffixFromFile($directory, $filePath);
            $checksum = hash_file('sha256', $filePath);

            if ($checksum === false) {
                throw new RuntimeException(sprintf('Gagal menghitung checksum migration: %s', $filePath));
            }

            $migrations[] = new MigrationDescriptor($scope, $package, $className, $filePath, $checksum);
        }

        return $migrations;
    }

    private function classSuffixFromFile(string $baseDirectory, string $filePath): string
    {
        $relativePath = ltrim(substr($filePath, strlen(rtrim($baseDirectory, '\\/'))), '\\/');
        $relativeClass = substr($relativePath, 0, -4);

        return str_replace(['/', '\\'], '\\', $relativeClass);
    }

    private function instantiateMigration(string $className): Migration
    {
        if (!class_exists($className)) {
            throw new RuntimeException(sprintf('Class migration tidak ditemukan: %s', $className));
        }

        if (!is_subclass_of($className, Migration::class)) {
            throw new RuntimeException(sprintf('Class %s bukan turunan Migration yang valid.', $className));
        }

        return new $className($this->connection, $this->app);
    }
}
