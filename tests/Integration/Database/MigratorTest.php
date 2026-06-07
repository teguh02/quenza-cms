<?php
declare(strict_types=1);

namespace Tests\Integration\Database;

use Quenza\Core\Database\Migrator;
use Tests\Support\DatabaseTestCase;

final class MigratorTest extends DatabaseTestCase
{
    public function test_migration_status_reports_all_core_migrations_as_ran(): void
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->get(Migrator::class);

        $status = $migrator->status();

        self::assertNotEmpty($status);
        self::assertCount(9, $status);

        foreach ($status as $row) {
            self::assertSame('Ran', $row['status']);
        }
    }
}
