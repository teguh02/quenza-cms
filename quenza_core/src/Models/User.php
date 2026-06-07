<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;
use Quenza\Core\Enums\UserStatus;

readonly class User extends Model
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $email,
        public string $passwordHash,
        public string $locale,
        public UserStatus $status,
        public int $failedLoginAttempts,
        public ?DateTimeImmutable $lockedUntil,
        public ?DateTimeImmutable $lastLoginAt,
        public ?string $rememberToken,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            self::intValue($attributes['id'] ?? null),
            self::stringValue($attributes['full_name'] ?? null),
            self::stringValue($attributes['email'] ?? null),
            self::stringValue($attributes['password_hash'] ?? null),
            self::stringValue($attributes['locale'] ?? 'id', 'id'),
            UserStatus::from(self::stringValue($attributes['status'] ?? UserStatus::Active->value, UserStatus::Active->value)),
            self::intValue($attributes['failed_login_attempts'] ?? 0),
            self::dateTimeValue($attributes['locked_until'] ?? null),
            self::dateTimeValue($attributes['last_login_at'] ?? null),
            self::nullableString($attributes['remember_token'] ?? null),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'locale' => $this->locale,
            'status' => $this->status->value,
            'failed_login_attempts' => $this->failedLoginAttempts,
            'locked_until' => self::formatDateTime($this->lockedUntil),
            'last_login_at' => self::formatDateTime($this->lastLoginAt),
            'remember_token' => $this->rememberToken,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
