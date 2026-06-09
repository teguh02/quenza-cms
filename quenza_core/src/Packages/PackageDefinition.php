<?php
declare(strict_types=1);

namespace Quenza\Core\Packages;

readonly class PackageDefinition
{
    public function __construct(
        public PackageScope $scope,
        public string $slug,
        public string $name,
        public string $version,
        public ?string $description,
        public ?string $author,
        public string $namespace,
        public string $basePath,
        public string $sourcePath,
        public ?string $migrationPath,
        public ?string $seederPath,
        public string $manifestPath,
    ) {
    }
}
