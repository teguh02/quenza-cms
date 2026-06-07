<?php
declare(strict_types=1);

namespace Quenza\Core\Database\Schema;

use Closure;
use Quenza\Core\Database\Connection;

final class SchemaManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($this->connection, $table);
        $callback($blueprint);

        $this->execute($blueprint->toSql());
    }

    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($this->connection, $table, true);
        $callback($blueprint);

        $this->execute($blueprint->toSql());
    }

    public function drop(string $table): void
    {
        $this->connection->pdo()->exec(sprintf('DROP TABLE %s', $this->connection->quotedTable($table)));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->pdo()->exec(sprintf('DROP TABLE IF EXISTS %s', $this->connection->quotedTable($table)));
    }

    public function hasTable(string $table): bool
    {
        return $this->connection->hasTable($table);
    }

    /**
     * @param list<string> $statements
     */
    private function execute(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->connection->pdo()->exec($statement);
        }
    }
}
