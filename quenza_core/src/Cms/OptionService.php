<?php
declare(strict_types=1);

namespace Quenza\Core\Cms;

use Quenza\Core\Database\DatabaseManager;

final class OptionService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (!$this->database->connection()->hasTable('options')) {
            return $default;
        }

        $record = $this->database->table('options')->where('option_name', $name)->first();

        return $record['option_value'] ?? $default;
    }

    public function set(string $name, mixed $value, bool $autoload = true): void
    {
        $this->database->updateOrInsert('options', [
            'option_name' => $name,
        ], [
            'option_value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR),
            'autoload' => $autoload ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
