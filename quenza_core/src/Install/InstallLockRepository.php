<?php
declare(strict_types=1);

namespace Quenza\Core\Install;

use Quenza\Core\Foundation\Application;
use RuntimeException;

final class InstallLockRepository
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function path(): string
    {
        return $this->app->basePath('storage/system/install.lock');
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $content = file_get_contents($this->path());

        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload): void
    {
        $directory = dirname($this->path());

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Gagal membuat direktori lock installer: %s', $directory));
        }

        $result = file_put_contents($this->path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        if ($result === false) {
            throw new RuntimeException('Gagal menulis install.lock.');
        }
    }

    public function delete(): void
    {
        if ($this->exists()) {
            unlink($this->path());
        }
    }
}
