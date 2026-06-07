<?php
declare(strict_types=1);

namespace Quenza\Core\Foundation;

final class Autoloader
{
    /**
     * @var array<string, list<string>>
     */
    private array $prefixes = [];

    public function addNamespace(string $prefix, string $baseDirectory): void
    {
        $normalizedPrefix = trim($prefix, '\\') . '\\';
        $normalizedBaseDirectory = rtrim($baseDirectory, '\\/') . DIRECTORY_SEPARATOR;

        $this->prefixes[$normalizedPrefix] ??= [];

        if (!in_array($normalizedBaseDirectory, $this->prefixes[$normalizedPrefix], true)) {
            $this->prefixes[$normalizedPrefix][] = $normalizedBaseDirectory;
        }

        uksort(
            $this->prefixes,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );
    }

    public function register(): void
    {
        spl_autoload_register($this->loadClass(...));
    }

    private function loadClass(string $className): void
    {
        foreach ($this->prefixes as $prefix => $directories) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            $relativeClass = substr($className, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            foreach ($directories as $directory) {
                $filePath = $directory . $relativePath;

                if (is_file($filePath)) {
                    require_once $filePath;

                    return;
                }
            }
        }
    }
}
