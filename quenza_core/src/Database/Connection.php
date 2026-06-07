<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use Closure;
use PDO;
use RuntimeException;
use Throwable;

final class Connection
{
    private ?PDO $pdo = null;

    private ?DatabaseDriver $driver = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        [$dsn, $username, $password] = $this->connectionCredentials();

        $this->pdo = new PDO($dsn, $username, $password, (array) ($this->config['options'] ?? []));

        if ($this->isSqlite()) {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }

        return $this->pdo;
    }

    public function driver(): DatabaseDriver
    {
        return $this->driver ??= DatabaseDriver::fromName((string) ($this->config['driver'] ?? 'sqlite'));
    }

    public function isMysql(): bool
    {
        return $this->driver() === DatabaseDriver::Mysql;
    }

    public function isSqlite(): bool
    {
        return $this->driver() === DatabaseDriver::Sqlite;
    }

    public function databaseName(): string
    {
        return $this->isMysql()
            ? (string) ($this->config['database'] ?? '')
            : (string) ($this->config['sqlite_path'] ?? '');
    }

    public function prefix(): string
    {
        return (string) ($this->config['prefix'] ?? 'qz_');
    }

    public function table(string $table): string
    {
        return str_starts_with($table, $this->prefix()) ? $table : $this->prefix() . $table;
    }

    public function quotedTable(string $table): string
    {
        return $this->quoteIdentifier($this->table($table));
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $parts = explode('.', $identifier);

        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new RuntimeException(sprintf('Identifier database tidak valid: %s', $identifier));
            }
        }

        $quote = $this->isSqlite() ? '"' : '`';

        return implode('.', array_map(
            static fn (string $part): string => $quote . $part . $quote,
            $parts,
        ));
    }

    /**
     * @return list<string>
     */
    public function listTablesByPrefix(?string $prefix = null): array
    {
        $resolvedPrefix = $prefix ?? $this->prefix();

        if ($this->isMysql()) {
            $statement = $this->pdo()->prepare(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME LIKE :prefix ORDER BY TABLE_NAME ASC',
            );

            $statement->execute([
                'schema' => $this->databaseName(),
                'prefix' => $resolvedPrefix . '%',
            ]);

            return array_values(array_filter(
                $statement->fetchAll(PDO::FETCH_COLUMN),
                'is_string',
            ));
        }

        $statement = $this->pdo()->prepare(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix AND name NOT LIKE 'sqlite_%' ORDER BY name ASC",
        );

        $statement->execute([
            'prefix' => $resolvedPrefix . '%',
        ]);

        return array_values(array_filter(
            $statement->fetchAll(PDO::FETCH_COLUMN),
            'is_string',
        ));
    }

    public function hasTable(string $table): bool
    {
        $resolvedTable = $this->table($table);

        if ($this->isMysql()) {
            $statement = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name',
            );

            $statement->execute([
                'schema' => (string) ($this->config['database'] ?? ''),
                'table_name' => $resolvedTable,
            ]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table_name",
        );

        $statement->execute(['table_name' => $resolvedTable]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function setForeignKeyChecks(bool $enabled): void
    {
        if ($this->isMysql()) {
            $this->pdo()->exec('SET FOREIGN_KEY_CHECKS=' . ($enabled ? '1' : '0'));

            return;
        }

        $this->pdo()->exec('PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF'));
    }

    public function transaction(Closure $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function connectionCredentials(): array
    {
        return match ($this->driver()) {
            DatabaseDriver::Mysql => [$this->mysqlDsn(), (string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? '')],
            DatabaseDriver::Sqlite => ['sqlite:' . $this->sqlitePath(), null, null],
        };
    }

    private function mysqlDsn(): string
    {
        $database = (string) ($this->config['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Nama database MySQL belum dikonfigurasi.');
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 3306),
            $database,
            (string) ($this->config['charset'] ?? 'utf8mb4'),
        );
    }

    private function sqlitePath(): string
    {
        $path = trim((string) ($this->config['sqlite_path'] ?? ''));

        if ($path === '') {
            throw new RuntimeException('Path database SQLite belum dikonfigurasi.');
        }

        $resolvedPath = $this->isAbsolutePath($path)
            ? $path
            : getcwd() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $directory = dirname($resolvedPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Gagal menyiapkan direktori database SQLite: %s', $directory));
        }

        return $resolvedPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:\\\\/', $path) === 1
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\');
    }
}
