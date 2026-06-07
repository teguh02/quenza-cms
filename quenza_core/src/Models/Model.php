<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;
use DateTimeInterface;

abstract readonly class Model
{
    protected static function intValue(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected static function stringValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    protected static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    protected static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower((string) $value)) {
            '1', 'true', 'yes', 'on' => true,
            default => false,
        };
    }

    protected static function dateTimeValue(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }

    protected static function formatDateTime(?DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, scalar|null>
     */
    abstract public function toArray(): array;
}
