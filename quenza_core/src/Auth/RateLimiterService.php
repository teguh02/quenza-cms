<?php
declare(strict_types=1);

namespace Quenza\Core\Auth;

use DateInterval;
use DateTimeImmutable;
use Quenza\Core\Database\DatabaseManager;

final class RateLimiterService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {
    }

    public function lockedUntil(string $action, string $ipAddress, ?string $identifier = null): ?DateTimeImmutable
    {
        $record = $this->database->table('auth_attempts')->where('throttle_key', $this->throttleKey($action, $ipAddress, $identifier))->first();

        if ($record === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $lastAttemptAt = $this->dateTimeValue($record['last_attempt_at'] ?? null);
        $lockedUntil = $this->dateTimeValue($record['locked_until'] ?? null);

        if ($lastAttemptAt !== null && $lastAttemptAt < $now->sub(new DateInterval('PT' . $this->lockSeconds($action) . 'S'))) {
            $this->clear($action, $ipAddress, $identifier);

            return null;
        }

        if ($lockedUntil !== null && $lockedUntil > $now) {
            return $lockedUntil;
        }

        return null;
    }

    public function hit(string $action, string $ipAddress, ?string $identifier = null): void
    {
        $throttleKey = $this->throttleKey($action, $ipAddress, $identifier);
        $record = $this->database->table('auth_attempts')->where('throttle_key', $throttleKey)->first();
        $now = new DateTimeImmutable();

        if ($record === null) {
            $this->database->insert('auth_attempts', [
                'throttle_key' => $throttleKey,
                'action' => $action,
                'ip_address' => $ipAddress,
                'identifier' => $identifier,
                'attempts' => 1,
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'locked_until' => null,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return;
        }

        $attempts = (int) ($record['attempts'] ?? 0) + 1;
        $lockedUntil = $attempts >= $this->maxAttempts($action)
            ? $now->add(new DateInterval('PT' . $this->lockSeconds($action) . 'S'))->format('Y-m-d H:i:s')
            : null;

        $this->database->update('auth_attempts', [
            'attempts' => $attempts,
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'locked_until' => $lockedUntil,
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ], [
            'throttle_key' => $throttleKey,
        ]);
    }

    public function clear(string $action, string $ipAddress, ?string $identifier = null): void
    {
        $this->database->delete('auth_attempts', [
            'throttle_key' => $this->throttleKey($action, $ipAddress, $identifier),
        ]);
    }

    private function throttleKey(string $action, string $ipAddress, ?string $identifier = null): string
    {
        return hash('sha256', strtolower(trim($action . '|' . $ipAddress . '|' . ($identifier ?? ''))));
    }

    private function maxAttempts(string $action): int
    {
        return match ($action) {
            'login' => 5,
            'register' => 3,
            default => 5,
        };
    }

    private function lockSeconds(string $action): int
    {
        return match ($action) {
            'login' => 900,
            'register' => 600,
            default => 600,
        };
    }

    private function dateTimeValue(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
