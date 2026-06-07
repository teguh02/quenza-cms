<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;

readonly class UserRole extends Model
{
    public function __construct(
        public int $userId,
        public int $roleId,
        public ?DateTimeImmutable $assignedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            self::intValue($attributes['user_id'] ?? null),
            self::intValue($attributes['role_id'] ?? null),
            self::dateTimeValue($attributes['assigned_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'role_id' => $this->roleId,
            'assigned_at' => self::formatDateTime($this->assignedAt),
        ];
    }
}
