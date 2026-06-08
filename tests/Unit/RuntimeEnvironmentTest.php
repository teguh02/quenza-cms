<?php
declare(strict_types=1);

namespace Tests\Unit;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Foundation\Autoloader;
use Quenza\Core\Runtime\RuntimeEnvironment;
use Tests\Support\TestCase;

final class RuntimeEnvironmentTest extends TestCase
{
    public function test_runtime_environment_respects_docker_override(): void
    {
        $application = new Application(
            dirname(__DIR__, 2),
            new Autoloader(),
            ['app' => ['runtime' => 'docker']],
        );

        $runtime = new RuntimeEnvironment($application);

        self::assertTrue($runtime->isDocker());
        self::assertSame('docker', $runtime->context());
    }

    public function test_runtime_environment_respects_host_override(): void
    {
        $application = new Application(
            dirname(__DIR__, 2),
            new Autoloader(),
            ['app' => ['runtime' => 'host']],
        );

        $runtime = new RuntimeEnvironment($application);

        self::assertTrue($runtime->isHostInstall());
        self::assertSame('host', $runtime->context());
    }
}
