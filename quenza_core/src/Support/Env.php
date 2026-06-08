<?php
declare(strict_types=1);

namespace Quenza\Core\Support;

use RuntimeException;

final class Env
{
    public static function load(string $filePath, bool $overrideExisting = false): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException(sprintf('Gagal membaca file environment: %s', $filePath));
        }

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#') || !str_contains($trimmedLine, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $trimmedLine, 2);

            self::set(trim($name), self::normalizeValue($value), $overrideExisting);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value !== false ? $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower((string) $value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    private static function set(string $key, string $value, bool $overrideExisting = false): void
    {
        if ($key === '') {
            return;
        }

        if (!$overrideExisting && self::get($key) !== null) {
            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv(sprintf('%s=%s', $key, $value));
    }

    private static function normalizeValue(string $value): string
    {
        $trimmedValue = trim($value);

        if (
            (str_starts_with($trimmedValue, '"') && str_ends_with($trimmedValue, '"'))
            || (str_starts_with($trimmedValue, "'") && str_ends_with($trimmedValue, "'"))
        ) {
            return substr($trimmedValue, 1, -1);
        }

        return $trimmedValue;
    }
}
