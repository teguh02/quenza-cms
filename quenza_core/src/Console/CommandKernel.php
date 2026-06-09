<?php
declare(strict_types=1);

namespace Quenza\Core\Console;

use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Packages\PackageScope;
use Throwable;

final class CommandKernel
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';
        $options = $this->parseOptions(array_slice($arguments, 2));

        try {
            return match ($command) {
                'migrate' => $this->handleMigrate($options),
                'migrate:rollback' => $this->handleRollback($options),
                'migrate:status' => $this->handleStatus($options),
                'migrate:fresh' => $this->handleFresh($options),
                'db:seed' => $this->handleSeed($options),
                'bundle:sync' => $this->handleBundleSync($options),
                'help', '--help', '-h' => $this->renderHelp(),
                default => $this->renderUnknownCommand($command),
            };
        } catch (Throwable $throwable) {
            $this->writeError($throwable->getMessage());

            if ((bool) $this->app->config('app.debug', false)) {
                $this->writeError($throwable->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleMigrate(array $options): int
    {
        $count = $this->app->get(Migrator::class)->migrate(
            $this->resolveScope($options['scope'] ?? null),
            isset($options['package']) ? (string) $options['package'] : null,
        );

        $this->writeLine(sprintf('Migrasi selesai. %d file dijalankan.', $count));

        if (($options['seed'] ?? false) === true) {
            $seedCount = $this->app->get(SeederRunner::class)->run(
                $this->resolveScope($options['scope'] ?? null),
                isset($options['package']) ? (string) $options['package'] : null,
            );

            $this->writeLine(sprintf('Seeder selesai. %d class dijalankan.', $seedCount));
        }

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleRollback(array $options): int
    {
        $steps = isset($options['steps']) ? max(1, (int) $options['steps']) : 1;

        $count = $this->app->get(Migrator::class)->rollback(
            $steps,
            $this->resolveScope($options['scope'] ?? null),
            isset($options['package']) ? (string) $options['package'] : null,
        );

        $this->writeLine(sprintf('Rollback selesai. %d file dibatalkan.', $count));

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleStatus(array $options): int
    {
        $rows = $this->app->get(Migrator::class)->status(
            $this->resolveScope($options['scope'] ?? null),
            isset($options['package']) ? (string) $options['package'] : null,
        );

        if ($rows === []) {
            $this->writeLine('Belum ada migration yang terdeteksi.');

            return 0;
        }

        $this->writeLine(str_pad('Scope', 12) . str_pad('Package', 20) . str_pad('Status', 12) . 'Migration');
        $this->writeLine(str_repeat('-', 80));

        foreach ($rows as $row) {
            $this->writeLine(
                str_pad((string) $row['scope'], 12)
                . str_pad((string) $row['package'], 20)
                . str_pad((string) $row['status'], 12)
                . (string) $row['migration'],
            );
        }

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleFresh(array $options): int
    {
        if (isset($options['scope']) || isset($options['package'])) {
            $this->writeError('Perintah migrate:fresh hanya mendukung reset penuh database inti pada Tahap 1.');

            return 1;
        }

        $count = $this->app->get(Migrator::class)->fresh();
        $this->writeLine(sprintf('Fresh migration selesai. %d file dijalankan.', $count));

        if (($options['seed'] ?? false) === true) {
            $seedCount = $this->app->get(SeederRunner::class)->run();
            $this->writeLine(sprintf('Seeder selesai. %d class dijalankan.', $seedCount));
        }

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleSeed(array $options): int
    {
        $count = $this->app->get(SeederRunner::class)->run(
            $this->resolveScope($options['scope'] ?? null),
            isset($options['package']) ? (string) $options['package'] : null,
            isset($options['class']) ? (string) $options['class'] : null,
        );

        $this->writeLine(sprintf('Seeder selesai. %d class dijalankan.', $count));

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function handleBundleSync(array $options): int
    {
        $this->writeLine('Sinkronisasi library pihak ketiga dari vendor/ ke quenza_core/libs/ ...');

        $vendorTwig = $this->app->basePath('vendor/twig/twig/src');
        $libsTwig = $this->app->basePath('quenza_core/libs/twig/src');

        $vendorHtmlPurifier = $this->app->basePath('vendor/ezyang/htmlpurifier/library');
        $libsHtmlPurifier = $this->app->basePath('quenza_core/libs/htmlpurifier/library');

        $success = true;

        if (is_dir($vendorTwig)) {
            $this->copyDirectory($vendorTwig, $libsTwig);
            $this->writeLine('[OK] Twig berhasil disinkronisasi.');
        } else {
            $this->writeError('[FAIL] Gagal sinkronisasi Twig: vendor/twig/twig/src tidak ditemukan.');
            $success = false;
        }

        if (is_dir($vendorHtmlPurifier)) {
            $this->copyDirectory($vendorHtmlPurifier, $libsHtmlPurifier);
            $this->writeLine('[OK] HTMLPurifier berhasil disinkronisasi.');
        } else {
            $this->writeError('[FAIL] Gagal sinkronisasi HTMLPurifier: vendor/ezyang/htmlpurifier/library tidak ditemukan.');
            $success = false;
        }

        return $success ? 0 : 1;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function renderHelp(): int
    {
        $this->writeLine('Quenza CMS CLI');
        $this->writeLine('');
        $this->writeLine('Perintah tersedia:');
        $this->writeLine('  php bin/qz migrate');
        $this->writeLine('  php bin/qz migrate --seed');
        $this->writeLine('  php bin/qz migrate --scope=plugin --package=blog-plugin');
        $this->writeLine('  php bin/qz migrate:rollback --steps=1');
        $this->writeLine('  php bin/qz migrate:status');
        $this->writeLine('  php bin/qz migrate:fresh --seed');
        $this->writeLine('  php bin/qz db:seed');
        $this->writeLine('  php bin/qz db:seed --scope=theme --package=default');
        $this->writeLine('  php bin/qz bundle:sync');

        return 0;
    }

    private function renderUnknownCommand(string $command): int
    {
        $this->writeError(sprintf('Perintah tidak dikenal: %s', $command));

        return $this->renderHelp();
    }

    /**
     * @param list<string> $arguments
     * @return array<string, string|bool>
     */
    private function parseOptions(array $arguments): array
    {
        $options = [];

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                continue;
            }

            $option = substr($argument, 2);

            if ($option === false || $option === '') {
                continue;
            }

            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $options[$key] = $value;

                continue;
            }

            $options[$option] = true;
        }

        return $options;
    }

    private function resolveScope(string|bool|null $scope): ?PackageScope
    {
        if (!is_string($scope) || trim($scope) === '') {
            return null;
        }

        return match ($scope) {
            'core' => PackageScope::Core,
            'plugin' => PackageScope::Plugin,
            'theme' => PackageScope::Theme,
            default => throw new \InvalidArgumentException(sprintf('Scope tidak valid: %s', $scope)),
        };
    }

    private function writeLine(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private function writeError(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
