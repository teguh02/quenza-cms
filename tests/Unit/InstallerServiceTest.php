<?php
declare(strict_types=1);

namespace Tests\Unit;

use Quenza\Core\Install\InstallerService;
use Tests\Support\TestCase;

final class InstallerServiceTest extends TestCase
{
    public function test_sqlite_database_configuration_is_valid_by_default(): void
    {
        /** @var InstallerService $installer */
        $installer = $this->app->get(InstallerService::class);

        $result = $installer->validateDatabaseConfiguration([
            'driver' => 'sqlite',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame('sqlite', $result['data']['driver']);
    }

    public function test_site_configuration_rejects_weak_admin_password(): void
    {
        /** @var InstallerService $installer */
        $installer = $this->app->get(InstallerService::class);

        $result = $installer->validateSiteConfiguration([
            'site_title' => 'Quenza Demo Site',
            'admin_username' => 'adminquenza',
            'admin_email' => 'admin@quenza.test',
            'admin_password' => 'weak',
        ]);

        self::assertFalse($result['valid']);
        self::assertArrayHasKey('admin_password', $result['errors']);
    }
}
