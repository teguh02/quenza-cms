<?php
declare(strict_types=1);

namespace Quenza\Core\Models;

use DateTimeImmutable;
use Quenza\Core\Enums\MenuItemType;

readonly class MenuItem extends Model
{
    public function __construct(
        public int $id,
        public int $menuId,
        public ?int $parentId,
        public string $title,
        public string $url,
        public string $target,
        public ?string $icon,
        public MenuItemType $itemType,
        public ?int $referenceId,
        public ?string $cssClass,
        public int $sortOrder,
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
            self::intValue($attributes['menu_id'] ?? null),
            self::nullableInt($attributes['parent_id'] ?? null),
            self::stringValue($attributes['title'] ?? null),
            self::stringValue($attributes['url'] ?? null),
            self::stringValue($attributes['target'] ?? '_self', '_self'),
            self::nullableString($attributes['icon'] ?? null),
            MenuItemType::from(self::stringValue($attributes['item_type'] ?? MenuItemType::Custom->value, MenuItemType::Custom->value)),
            self::nullableInt($attributes['reference_id'] ?? null),
            self::nullableString($attributes['css_class'] ?? null),
            self::intValue($attributes['sort_order'] ?? 0),
            self::dateTimeValue($attributes['created_at'] ?? null),
            self::dateTimeValue($attributes['updated_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menuId,
            'parent_id' => $this->parentId,
            'title' => $this->title,
            'url' => $this->url,
            'target' => $this->target,
            'icon' => $this->icon,
            'item_type' => $this->itemType->value,
            'reference_id' => $this->referenceId,
            'css_class' => $this->cssClass,
            'sort_order' => $this->sortOrder,
            'created_at' => self::formatDateTime($this->createdAt),
            'updated_at' => self::formatDateTime($this->updatedAt),
        ];
    }
}
