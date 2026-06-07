<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Quenza\Core\Packages\PackageScope;

readonly class MigrationDescriptor
{
    public function __construct(
        public PackageScope $scope,
        public string $package,
        public string $className,
        public string $filePath,
        public string $checksum,
    ) {
    }

    public function key(): string
    {
        return sprintf('%s:%s:%s', $this->scope->value, $this->package, $this->className);
    }
}
