<?php
declare(strict_types=1);

namespace Quenza\Core\Foundation;

use Closure;
use InvalidArgumentException;
use Quenza\Core\Support\Arr;

final class Application
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, Closure(self): mixed>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    public function __construct(
        private readonly string $basePath,
        private readonly Autoloader $autoloader,
        array $config,
    ) {
        $this->config = $config;
    }

    public static function setInstance(self $application): void
    {
        self::$instance = $application;
    }

    public static function getInstance(): self
    {
        return self::$instance ?? throw new InvalidArgumentException('Aplikasi Quenza belum di-bootstrap.');
    }

    public function basePath(string $path = ''): string
    {
        if ($path === '') {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '\\/');
    }

    public function autoloader(): Autoloader
    {
        return $this->autoloader;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function singleton(string $identifier, Closure $resolver): void
    {
        $this->bindings[$identifier] = $resolver;
        unset($this->instances[$identifier]);
    }

    public function instance(string $identifier, mixed $instance): void
    {
        $this->instances[$identifier] = $instance;
    }

    public function get(string $identifier): mixed
    {
        if (array_key_exists($identifier, $this->instances)) {
            return $this->instances[$identifier];
        }

        if (!array_key_exists($identifier, $this->bindings)) {
            throw new InvalidArgumentException(sprintf('Service "%s" belum terdaftar.', $identifier));
        }

        return $this->instances[$identifier] = ($this->bindings[$identifier])($this);
    }
}
