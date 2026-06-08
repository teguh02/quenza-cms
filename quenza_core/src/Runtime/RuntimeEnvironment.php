<?php
declare(strict_types=1);

namespace Quenza\Core\Runtime;

use Quenza\Core\Foundation\Application;

final class RuntimeEnvironment
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function context(): string
    {
        $override = strtolower(trim((string) $this->app->config('app.runtime', 'auto')));

        if (in_array($override, ['docker', 'host'], true)) {
            return $override;
        }

        return $this->detectDocker() ? 'docker' : 'host';
    }

    public function isDocker(): bool
    {
        return $this->context() === 'docker';
    }

    public function isHostInstall(): bool
    {
        return $this->context() === 'host';
    }

    public function usesManualOverride(): bool
    {
        return in_array(strtolower(trim((string) $this->app->config('app.runtime', 'auto'))), ['docker', 'host'], true);
    }

    public function hasManualEnvironmentFile(): bool
    {
        return is_file($this->app->basePath('.env'));
    }

    private function detectDocker(): bool
    {
        if (is_file('/.dockerenv')) {
            return true;
        }

        $cgroupFile = DIRECTORY_SEPARATOR === '/' ? '/proc/1/cgroup' : null;

        if ($cgroupFile !== null && is_file($cgroupFile)) {
            $contents = file_get_contents($cgroupFile);

            if ($contents !== false && preg_match('/docker|containerd|kubepods/i', $contents) === 1) {
                return true;
            }
        }

        $containerHint = (string) (getenv('CONTAINER') ?: ($_ENV['CONTAINER'] ?? $_SERVER['CONTAINER'] ?? ''));

        return in_array(strtolower($containerHint), ['docker', 'container'], true);
    }
}
