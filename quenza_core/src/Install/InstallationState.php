<?php
declare(strict_types=1);

namespace Quenza\Core\Install;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\Connection;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Session\SessionManager;
use Throwable;

final class InstallationState
{
    public function __construct(
        private readonly Application $app,
        private readonly Connection $connection,
        private readonly OptionService $options,
        private readonly SessionManager $session,
    ) {
    }

    public function requiresInstallation(): bool
    {
        if ((string) $this->app->config('app.env', 'production') === 'testing') {
            return false;
        }

        if (!$this->hasEnvironmentFile()) {
            return true;
        }

        try {
            $this->connection->pdo();
        } catch (Throwable) {
            return true;
        }

        foreach (['users', 'roles', 'options', 'posts', 'categories'] as $table) {
            if (!$this->connection->hasTable($table)) {
                return true;
            }
        }

        return trim((string) $this->options->get('installation_completed_at', '')) === '';
    }

    public function hasEnvironmentFile(): bool
    {
        return is_file($this->environmentFilePath());
    }

    public function environmentFilePath(): string
    {
        return $this->app->basePath('.env');
    }

    public function shouldRedirectToInstall(string $path): bool
    {
        return $this->requiresInstallation() && !str_starts_with($path, '/install');
    }

    public function shouldRedirectFromInstall(string $path): bool
    {
        if ($path === '/install/success' && $this->session->has('installer_success')) {
            return false;
        }

        return !$this->requiresInstallation() && str_starts_with($path, '/install');
    }

    public function postInstallRedirect(): string
    {
        if ($this->app->has(AuthManager::class) && $this->app->get(AuthManager::class)->check()) {
            return '/admin';
        }

        return '/login';
    }
}
