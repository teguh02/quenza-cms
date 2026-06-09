<?php
declare(strict_types=1);

namespace Quenza\Core\Session;

use Quenza\Core\Foundation\Application;

final class SessionManager
{
    private const string AUTH_USER_KEY = '_qz_auth_user_id';
    private const string FLASH_KEY = '_qz_flash';
    private const string FLASH_NEW_KEY = 'new';
    private const string FLASH_OLD_KEY = 'old';

    private bool $started = false;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_name((string) $this->app->config('app.session_name', 'QUENZASESSID'));
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => str_starts_with((string) $this->app->config('app.url', ''), 'https://'),
                'use_strict_mode' => true,
            ]);
        }

        $_SESSION[self::FLASH_KEY] ??= [
            self::FLASH_NEW_KEY => [],
            self::FLASH_OLD_KEY => [],
        ];

        $this->sweepFlashData();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);

        $flash = $_SESSION[self::FLASH_KEY];

        if (!in_array($key, $flash[self::FLASH_NEW_KEY], true)) {
            $flash[self::FLASH_NEW_KEY][] = $key;
        }

        $_SESSION[self::FLASH_KEY] = $flash;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }

    public function oldInput(string $key, mixed $default = null): mixed
    {
        $input = $this->get('_old_input', []);

        return is_array($input) && array_key_exists($key, $input) ? $input[$key] : $default;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        $errors = $this->get('errors', []);

        return is_array($errors) ? $errors : [];
    }

    public function flashErrors(array $errors): void
    {
        $this->flash('errors', $errors);
    }

    public function authUserId(): ?int
    {
        $value = $this->get(self::AUTH_USER_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    public function setAuthUserId(int $userId): void
    {
        $this->put(self::AUTH_USER_KEY, $userId);
    }

    public function forgetAuthUserId(): void
    {
        $this->forget(self::AUTH_USER_KEY);
    }

    public function regenerate(): void
    {
        $this->start();

        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public function invalidate(): void
    {
        $this->start();
        $_SESSION = [];

        if (PHP_SAPI !== 'cli') {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
            }

            session_destroy();
        }

        $this->started = false;
        $this->start();
    }

    private function sweepFlashData(): void
    {
        $flash = $_SESSION[self::FLASH_KEY];

        foreach ($flash[self::FLASH_OLD_KEY] as $key) {
            unset($_SESSION[$key]);
        }

        $_SESSION[self::FLASH_KEY] = [
            self::FLASH_NEW_KEY => [],
            self::FLASH_OLD_KEY => $flash[self::FLASH_NEW_KEY] ?? [],
        ];
    }
}
