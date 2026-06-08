<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;

readonly class ActivityLog extends Model
{
    public function __construct(
        public int $id,
        public ?int $actorUserId,
        public string $action,
        public ?string $subjectType,
        public ?int $subjectId,
        public string $description,
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
            self::nullableInt($attributes['actor_user_id'] ?? null),
            self::stringValue($attributes['action'] ?? null),
            self::nullableString($attributes['subject_type'] ?? null),
            self::nullableInt($attributes['subject_id'] ?? null),
            self::stringValue($attributes['description'] ?? null),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'actor_user_id' => $this->actorUserId,
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'description' => $this->description,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
