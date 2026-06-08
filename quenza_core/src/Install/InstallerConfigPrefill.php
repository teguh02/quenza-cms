<?php
declare(strict_types=1);

namespace Quenza\Core\Install;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Runtime\RuntimeEnvironment;

final class InstallerConfigPrefill
{
    public function __construct(
        private readonly Application $app,
        private readonly RuntimeEnvironment $runtime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $appUrl = (string) $this->app->config('app.url', 'http://localhost');
        $parsedUrl = parse_url($appUrl);
        $scheme = (string) ($parsedUrl['scheme'] ?? 'http');
        $host = (string) ($parsedUrl['host'] ?? 'localhost');
        $publicPort = isset($parsedUrl['port']) ? (int) $parsedUrl['port'] : ($scheme === 'https' ? 443 : 80);

        return [
            'manual_configuration_detected' => $this->runtime->hasManualEnvironmentFile(),
            'runtime_context' => $this->runtime->context(),
            'locale' => (string) $this->app->config('app.locale', 'id'),
            'database' => [
                'driver' => (string) $this->app->config('database.driver', 'sqlite'),
                'host' => (string) $this->app->config('database.host', '127.0.0.1'),
                'port' => (int) $this->app->config('database.port', 3306),
                'database' => (string) $this->app->config('database.database', 'quenza_cms'),
                'username' => (string) $this->app->config('database.username', 'root'),
                'password' => (string) $this->env('DB_PASSWORD', ''),
                'sqlite_path' => $this->relativePath((string) $this->app->config('database.sqlite_path', $this->app->basePath('storage/database/quenza.db'))),
                'public_scheme' => $scheme,
                'public_hostname' => $host,
                'app_public_port' => (int) $this->env('QZ_DOCKER_APP_PUBLIC_PORT', (string) $publicPort),
                'app_internal_port' => (int) $this->env('QZ_DOCKER_APP_INTERNAL_PORT', '80'),
                'db_published_port' => (int) $this->env('QZ_DOCKER_DB_PUBLISHED_PORT', (string) $this->app->config('database.port', 3306)),
            ],
            'site' => [
                'site_title' => (string) $this->app->config('app.name', 'Quenza CMS'),
                'admin_username' => (string) $this->env('QZ_ADMIN_USERNAME', ''),
                'admin_email' => (string) $this->env('QZ_ADMIN_EMAIL', ''),
                'admin_password' => (string) $this->env('QZ_ADMIN_PASSWORD', ''),
                'has_admin_password_prefill' => trim((string) $this->env('QZ_ADMIN_PASSWORD', '')) !== '',
            ],
        ];
    }

    private function env(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value !== false) {
            return (string) $value;
        }

        return array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : $default;
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim($this->app->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), DIRECTORY_SEPARATOR);
        }

        return $path;
    }
}
