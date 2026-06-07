<?php
declare(strict_types=1);

namespace Tests\Support;

use Quenza\Core\Database\Migrator;
use Quenza\Core\Database\SeederRunner;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }

    protected function refreshDatabase(bool $seed = true): void
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->get(Migrator::class);
        $migrator->fresh();

        if ($seed) {
            /** @var SeederRunner $seeders */
            $seeders = $this->app->get(SeederRunner::class);
            $seeders->run();
        }
    }
}
