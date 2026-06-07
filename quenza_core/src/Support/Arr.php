<?php
declare(strict_types=1);

namespace Quenza\Core\Support;

final class Arr
{
    public static function get(array $items, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $items;
        }

        if (array_key_exists($key, $items)) {
            return $items[$key];
        }

        $value = $items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
