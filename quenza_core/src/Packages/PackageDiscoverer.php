<?php
declare(strict_types=1);

namespace Quenza\Core\Packages;

use DirectoryIterator;

final class PackageDiscoverer
{
    public function __construct(
        private readonly string $basePath,
        private readonly ManifestValidator $validator,
        private readonly string $activeTheme,
    ) {
    }

    /**
     * @return list<PackageDefinition>
     */
    public function discoverPlugins(): array
    {
        return $this->discoverPackages(PackageScope::Plugin, 'qz_plugins', 'manifest.json');
    }

    /**
     * @return list<PackageDefinition>
     */
    public function discoverThemes(bool $activeOnly = true): array
    {
        $themes = $this->discoverPackages(PackageScope::Theme, 'qz_themes', 'theme.json');

        if (!$activeOnly) {
            return $themes;
        }

        return array_values(array_filter(
            $themes,
            fn (PackageDefinition $theme): bool => $theme->slug === $this->activeTheme,
        ));
    }

    /**
     * @return list<PackageDefinition>
     */
    public function discoverAll(bool $activeThemesOnly = true): array
    {
        return [
            ...$this->discoverPlugins(),
            ...$this->discoverThemes($activeThemesOnly),
        ];
    }

    /**
     * @return list<PackageDefinition>
     */
    private function discoverPackages(PackageScope $scope, string $directory, string $manifestFile): array
    {
        $packagesDirectory = $this->basePath . DIRECTORY_SEPARATOR . $directory;

        if (!is_dir($packagesDirectory)) {
            return [];
        }

        $packages = [];

        foreach (new DirectoryIterator($packagesDirectory) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $manifestPath = $entry->getPathname() . DIRECTORY_SEPARATOR . $manifestFile;

            if (!is_file($manifestPath)) {
                continue;
            }

            $packages[] = $this->validator->validateManifestFile($manifestPath, $scope);
        }

        usort(
            $packages,
            static fn (PackageDefinition $left, PackageDefinition $right): int => $left->slug <=> $right->slug,
        );

        return $packages;
    }
}
