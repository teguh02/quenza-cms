<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;
use Quenza\Core\Enums\PostStatus;
use Quenza\Core\Enums\PostType;

readonly class Post extends Model
{
    public function __construct(
        public int $id,
        public ?int $authorId,
        public ?int $parentId,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $content,
        public PostType $postType,
        public PostStatus $status,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $trashedAt,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $metaKeywords,
        public ?string $ogImage,
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
            self::nullableInt($attributes['author_id'] ?? null),
            self::nullableInt($attributes['parent_id'] ?? null),
            self::stringValue($attributes['title'] ?? null),
            self::stringValue($attributes['slug'] ?? null),
            self::nullableString($attributes['excerpt'] ?? null),
            self::nullableString($attributes['content'] ?? null),
            PostType::from(self::stringValue($attributes['post_type'] ?? PostType::Post->value, PostType::Post->value)),
            PostStatus::from(self::stringValue($attributes['status'] ?? PostStatus::Draft->value, PostStatus::Draft->value)),
            self::dateTimeValue($attributes['published_at'] ?? null),
            self::dateTimeValue($attributes['trashed_at'] ?? null),
            self::nullableString($attributes['meta_title'] ?? null),
            self::nullableString($attributes['meta_description'] ?? null),
            self::nullableString($attributes['meta_keywords'] ?? null),
            self::nullableString($attributes['og_image'] ?? null),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'post_type' => $this->postType->value,
            'status' => $this->status->value,
            'published_at' => self::formatDateTime($this->publishedAt),
            'trashed_at' => self::formatDateTime($this->trashedAt),
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'meta_keywords' => $this->metaKeywords,
            'og_image' => $this->ogImage,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
