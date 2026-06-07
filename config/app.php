<?php
declare(strict_types=1);

use Quenza\Core\Support\Env;

return [
    'name' => Env::string('APP_NAME', 'Quenza CMS'),
    'env' => Env::string('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => Env::string('APP_URL', 'http://localhost'),
    'timezone' => Env::string('APP_TIMEZONE', 'UTC'),
    'locale' => Env::string('APP_LOCALE', 'id'),
    'fallback_locale' => Env::string('APP_FALLBACK_LOCALE', 'en'),
    'session_name' => Env::string('SESSION_NAME', 'QUENZASESSID'),
    'active_theme' => Env::string('QZ_ACTIVE_THEME', 'default'),
];
