<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Quenza\Core\Foundation\Application;

abstract class Seeder
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly Application $app,
    ) {
    }

    abstract public function run(): void;

    final protected function db(): DatabaseManager
    {
        /** @var DatabaseManager $database */
        $database = $this->app->get(DatabaseManager::class);

        return $database;
    }

    protected function call(string $seederClass): void
    {
        $seeder = new $seederClass($this->connection, $this->app);

        if (!$seeder instanceof self) {
            throw new \RuntimeException(sprintf('Class %s bukan seeder yang valid.', $seederClass));
        }

        $seeder->run();
    }
}
