<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use PDO;
use Quenza\Core\Packages\PackageScope;
use Quenza\Core\Database\Schema\SchemaManager;
use Quenza\Core\Database\Schema\Blueprint;

final class MigrationRepository
{
    private bool $repositoryEnsured = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly DatabaseManager $database,
        private readonly SchemaManager $schema,
    ) {
    }

    public function ensureRepository(): void
    {
        if ($this->repositoryEnsured || $this->schema->hasTable('migrations')) {
            $this->repositoryEnsured = true;

            return;
        }

        $this->schema->create('migrations', static function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20);
            $table->string('package', 150);
            $table->string('migration', 255);
            $table->string('checksum', 64);
            $table->integer('batch')->unsigned();
            $table->timestamp('executed_at')->useCurrent();
            $table->unique(['scope', 'package', 'migration'], 'uq_qz_migrations');
            $table->index('batch', 'idx_qz_migrations_batch');
        });

        $this->repositoryEnsured = true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function executedIndex(?PackageScope $scope = null, ?string $package = null): array
    {
        $this->ensureRepository();

        $conditions = [];
        $parameters = [];

        if ($scope !== null) {
            $conditions[] = 'scope = :scope';
            $parameters['scope'] = $scope->value;
        }

        if ($package !== null) {
            $conditions[] = 'package = :package';
            $parameters['package'] = $package;
        }

        $sql = sprintf(
            'SELECT scope, package, migration, checksum, batch, executed_at
             FROM %s%s',
            $this->connection->quotedTable('migrations'),
            $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions),
        );

        $statement = $this->database->query($sql, $parameters);

        $records = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $records[sprintf('%s:%s:%s', $row['scope'], $row['package'], $row['migration'])] = $row;
        }

        return $records;
    }

    public function nextBatchNumber(): int
    {
        $this->ensureRepository();

        $statement = $this->database->query(sprintf(
            'SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM %s',
            $this->connection->quotedTable('migrations'),
        ));

        return (int) $statement->fetchColumn();
    }

    public function log(MigrationDescriptor $migration, int $batch): void
    {
        $this->ensureRepository();

        $statement = $this->database->query(sprintf(
            'INSERT INTO %s (scope, package, migration, checksum, batch) VALUES (:scope, :package, :migration, :checksum, :batch)',
            $this->connection->quotedTable('migrations'),
        ), [
            'scope' => $migration->scope->value,
            'package' => $migration->package,
            'migration' => $migration->className,
            'checksum' => $migration->checksum,
            'batch' => $batch,
        ]);
    }

    public function remove(PackageScope $scope, string $package, string $migrationClass): void
    {
        $this->database->query(sprintf(
            'DELETE FROM %s WHERE scope = :scope AND package = :package AND migration = :migration',
            $this->connection->quotedTable('migrations'),
        ), [
            'scope' => $scope->value,
            'package' => $package,
            'migration' => $migrationClass,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rollbackCandidates(int $steps = 1, ?PackageScope $scope = null, ?string $package = null): array
    {
        $this->ensureRepository();

        $conditions = [];
        $parameters = [];

        if ($scope !== null) {
            $conditions[] = 'scope = :scope';
            $parameters['scope'] = $scope->value;
        }

        if ($package !== null) {
            $conditions[] = 'package = :package';
            $parameters['package'] = $package;
        }

        $whereClause = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $batchStatement = $this->database->query(sprintf(
            'SELECT DISTINCT batch FROM %s%s ORDER BY batch DESC LIMIT %d',
            $this->connection->quotedTable('migrations'),
            $whereClause,
            max(1, $steps),
        ), $parameters);
        $batches = array_map('intval', $batchStatement->fetchAll(PDO::FETCH_COLUMN));

        if ($batches === []) {
            return [];
        }

        $batchConditions = [];
        $rowParameters = $parameters;

        foreach ($batches as $index => $batch) {
            $parameterName = 'batch_' . $index;
            $batchConditions[] = 'batch = :' . $parameterName;
            $rowParameters[$parameterName] = $batch;
        }

        $rowWhereClause = $whereClause === '' ? ' WHERE ' : $whereClause . ' AND ';
        $rowWhereClause .= '(' . implode(' OR ', $batchConditions) . ')';

        $rowStatement = $this->database->query(sprintf(
            'SELECT scope, package, migration, checksum, batch FROM %s%s ORDER BY batch DESC, id DESC',
            $this->connection->quotedTable('migrations'),
            $rowWhereClause,
        ), $rowParameters);

        return $rowStatement->fetchAll(PDO::FETCH_ASSOC);
    }
}
