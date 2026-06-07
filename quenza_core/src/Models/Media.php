<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;

readonly class Media extends Model
{
    public function __construct(
        public int $id,
        public ?int $uploaderId,
        public string $originalName,
        public string $storedName,
        public string $directory,
        public string $disk,
        public string $mimeType,
        public string $extension,
        public int $sizeBytes,
        public ?int $width,
        public ?int $height,
        public ?string $altText,
        public ?string $caption,
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
            self::nullableInt($attributes['uploader_id'] ?? null),
            self::stringValue($attributes['original_name'] ?? null),
            self::stringValue($attributes['stored_name'] ?? null),
            self::stringValue($attributes['directory'] ?? 'uploads', 'uploads'),
            self::stringValue($attributes['disk'] ?? 'local', 'local'),
            self::stringValue($attributes['mime_type'] ?? null),
            self::stringValue($attributes['extension'] ?? null),
            self::intValue($attributes['size_bytes'] ?? 0),
            self::nullableInt($attributes['width'] ?? null),
            self::nullableInt($attributes['height'] ?? null),
            self::nullableString($attributes['alt_text'] ?? null),
            self::nullableString($attributes['caption'] ?? null),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uploader_id' => $this->uploaderId,
            'original_name' => $this->originalName,
            'stored_name' => $this->storedName,
            'directory' => $this->directory,
            'disk' => $this->disk,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->altText,
            'caption' => $this->caption,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
