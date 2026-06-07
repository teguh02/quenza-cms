<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;

readonly class Option extends Model
{
    public function __construct(
        public int $id,
        public string $optionName,
        public ?string $optionValue,
        public bool $autoload,
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
            self::stringValue($attributes['option_name'] ?? null),
            self::nullableString($attributes['option_value'] ?? null),
            self::boolValue($attributes['autoload'] ?? true),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'option_name' => $this->optionName,
            'option_value' => $this->optionValue,
            'autoload' => $this->autoload ? 1 : 0,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
