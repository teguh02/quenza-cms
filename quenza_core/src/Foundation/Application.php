<?php
declare(strict_types=1);

namespace Quenza\Core\Foundation;

use Closure;
use InvalidArgumentException;
use Quenza\Core\Support\Arr;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

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

    public function has(string $identifier): bool
    {
        return array_key_exists($identifier, $this->instances)
            || array_key_exists($identifier, $this->bindings);
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

    public function make(string $identifier): mixed
    {
        if ($this->has($identifier)) {
            return $this->get($identifier);
        }

        try {
            $reflection = new ReflectionClass($identifier);
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException(sprintf('Class "%s" tidak dapat di-resolve.', $identifier), 0, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException(sprintf('Class "%s" tidak dapat diinstansiasi.', $identifier));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getParameters() === []) {
            return new $identifier();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();

                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Parameter "%s" pada class "%s" tidak dapat di-resolve.',
                $parameter->getName(),
                $identifier,
            ));
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
