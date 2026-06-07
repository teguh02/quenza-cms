<?php
declare(strict_types=1);

namespace Quenza\Core\Database;

use InvalidArgumentException;

enum DatabaseDriver: string
{
    case Mysql = 'mysql';
    case Sqlite = 'sqlite';

    public static function fromName(string $driver): self
    {
        return match (strtolower(trim($driver))) {
            self::Mysql->value => self::Mysql,
            self::Sqlite->value => self::Sqlite,
            default => throw new InvalidArgumentException(sprintf(
                'Driver database tidak didukung: %s. Quenza CMS hanya mendukung mysql dan sqlite.',
                $driver,
            )),
        };
    }
}
