<?php
declare(strict_types=1);

namespace Quenza\Core\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $request,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
        private array $routeParameters = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        $normalizedPath = $path === '' ? '/' : rtrim($path, '/');

        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        return new self(
            $method,
            $normalizedPath,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER,
        );
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public static function create(
        string $method,
        string $uri,
        array $query = [],
        array $request = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
    ): self {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $normalizedPath = $path === '' ? '/' : rtrim($path, '/');

        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        return new self(
            strtoupper($method),
            $normalizedPath,
            $query,
            $request,
            $cookies,
            $files,
            [
                'REQUEST_METHOD' => strtoupper($method),
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => $server['REMOTE_ADDR'] ?? '127.0.0.1',
                ...$server,
            ],
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        return [...$this->query, ...$this->request];
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->request)) {
                $values[$key] = $this->request[$key];
            }
        }

        return $values;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function withRouteParameters(array $parameters): self
    {
        $clone = clone $this;
        $clone->routeParameters = $parameters;

        return $clone;
    }

    public function ipAddress(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $this->server[$serverKey] ?? $default;
    }

    public function host(): string
    {
        $forwardedHost = $this->header('X-Forwarded-Host');

        if (is_string($forwardedHost) && trim($forwardedHost) !== '') {
            return trim(explode(',', $forwardedHost)[0]);
        }

        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function scheme(): string
    {
        $forwardedProto = $this->header('X-Forwarded-Proto');

        if (is_string($forwardedProto) && trim($forwardedProto) !== '') {
            return strtolower(trim(explode(',', $forwardedProto)[0])) === 'https' ? 'https' : 'http';
        }

        $https = (string) ($this->server['HTTPS'] ?? '');

        return $https !== '' && strtolower($https) !== 'off' ? 'https' : 'http';
    }

    public function baseUrl(): string
    {
        $host = $this->host();
        $scheme = $this->scheme();
        $forwardedPort = $this->header('X-Forwarded-Port');
        $port = is_string($forwardedPort) && trim($forwardedPort) !== '' ? (int) trim(explode(',', $forwardedPort)[0]) : null;

        if ($port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return $scheme . '://' . $host;
        }

        return $scheme . '://' . $host . ':' . $port;
    }

    /**
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }
}
