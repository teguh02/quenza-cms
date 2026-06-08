<?php
declare(strict_types=1);

namespace Tests\Unit;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Foundation\Autoloader;
use Quenza\Core\Install\InstallLockRepository;
use Tests\Support\TestCase;

final class InstallLockRepositoryTest extends TestCase
{
    public function test_install_lock_repository_can_write_read_and_delete_lock_file(): void
    {
        $tempBasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quenza-lock-' . uniqid('', true);
        mkdir($tempBasePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'system', 0755, true);

        $application = new Application(
            $tempBasePath,
            new Autoloader(),
            ['app' => ['runtime' => 'host']],
        );

        $repository = new InstallLockRepository($application);

        self::assertFalse($repository->exists());

        $repository->write([
            'installed_at' => '2026-06-08 00:00:00',
            'driver' => 'sqlite',
        ]);

        self::assertTrue($repository->exists());
        self::assertSame('sqlite', $repository->read()['driver']);

        $repository->delete();

        self::assertFalse($repository->exists());

        @rmdir($tempBasePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'system');
        @rmdir($tempBasePath . DIRECTORY_SEPARATOR . 'storage');
        @rmdir($tempBasePath);
    }
}
