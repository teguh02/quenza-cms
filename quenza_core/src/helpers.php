<?php
declare(strict_types=1);

use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Database\Schema\SchemaManager;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Translation\Translator;

if (!function_exists('app')) {
    function app(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()->config($key, $default);
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $replacements = [], ?string $locale = null): string
    {
        /** @var Translator $translator */
        $translator = app()->get(Translator::class);

        return $translator->translate($key, $replacements, $locale);
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replacements = [], ?string $locale = null): string
    {
        return trans($key, $replacements, $locale);
    }
}

if (!function_exists('db')) {
    function db(): DatabaseManager
    {
        /** @var DatabaseManager $database */
        $database = app()->get(DatabaseManager::class);

        return $database;
    }
}

if (!function_exists('schema')) {
    function schema(): SchemaManager
    {
        /** @var SchemaManager $schema */
        $schema = app()->get(SchemaManager::class);

        return $schema;
    }
}

if (!function_exists('transaction')) {
    function transaction(callable $callback): mixed
    {
        return db()->transaction(
            static fn (DatabaseManager $database): mixed => $callback($database),
        );
    }
}
