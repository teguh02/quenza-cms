<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;

readonly class Category extends Model
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
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
            self::stringValue($attributes['name'] ?? null),
            self::stringValue($attributes['slug'] ?? null),
            self::nullableString($attributes['description'] ?? null),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
