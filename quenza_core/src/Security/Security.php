<?php
declare(strict_types=1);

namespace Quenza\Core\Security;

use Quenza\Core\Foundation\Application;
use RuntimeException;

final class Security
{
    private const string CSRF_SESSION_KEY = '_qz_csrf_tokens';
    private const int CSRF_TTL = 7200;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function generateCsrfToken(string $tokenId = 'default'): string
    {
        $this->ensureSessionStarted();
        $this->purgeExpiredTokens();

        $token = bin2hex(random_bytes(32));

        $_SESSION[self::CSRF_SESSION_KEY][$tokenId] = [
            'value' => $token,
            'expires_at' => time() + self::CSRF_TTL,
        ];

        return $token;
    }

    public function validateCsrfToken(string $token, string $tokenId = 'default', bool $consume = true): bool
    {
        $this->ensureSessionStarted();
        $this->purgeExpiredTokens();

        $storedToken = $_SESSION[self::CSRF_SESSION_KEY][$tokenId] ?? null;

        if (!is_array($storedToken) || !isset($storedToken['value'])) {
            return false;
        }

        $isValid = hash_equals((string) $storedToken['value'], $token);

        if ($isValid && $consume) {
            unset($_SESSION[self::CSRF_SESSION_KEY][$tokenId]);
        }

        return $isValid;
    }

    public function csrfField(string $tokenId = 'default', string $fieldName = '_token'): string
    {
        $token = $this->generateCsrfToken($tokenId);

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
        );
    }

    public function sanitizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim($normalized);
    }

    public function sanitizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = filter_var(trim($value), FILTER_SANITIZE_EMAIL);

        return filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false ? (string) $sanitized : null;
    }

    public function sanitizeInt(mixed $value): ?int
    {
        $sanitized = filter_var($value, FILTER_VALIDATE_INT);

        return $sanitized === false ? null : (int) $sanitized;
    }

    public function sanitizeUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = filter_var(trim($value), FILTER_SANITIZE_URL);

        return filter_var($sanitized, FILTER_VALIDATE_URL) !== false ? (string) $sanitized : null;
    }

    public function sanitizeArray(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $sanitized[$key] = match (true) {
                is_array($value) => $this->sanitizeArray($value),
                is_string($value) => $this->sanitizeText($value),
                default => $value,
            };
        }

        return $sanitized;
    }

    public function sanitizeRichText(?string $html): string
    {
        if (!class_exists(\HTMLPurifier::class) || !class_exists(\HTMLPurifier_Config::class)) {
            throw new RuntimeException('HTMLPurifier belum terpasang. Jalankan composer install sebelum memproses rich text.');
        }

        $cachePath = $this->app->basePath('storage/cache/htmlpurifier');

        if (!is_dir($cachePath) && !mkdir($cachePath, 0755, true) && !is_dir($cachePath)) {
            throw new RuntimeException(sprintf('Gagal menyiapkan cache HTMLPurifier: %s', $cachePath));
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Doctype', 'HTML 5');
        $config->set('Attr.EnableID', false);
        $config->set('CSS.Trusted', false);
        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.SafeObject', false);

        $purifier = new \HTMLPurifier($config);

        return $purifier->purify($html ?? '');
    }

    public function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        if ($hash === false) {
            throw new RuntimeException('Gagal membuat hash kata sandi.');
        }

        return $hash;
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    private function ensureSessionStarted(): void
    {
        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];

            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_name((string) $this->app->config('app.session_name', 'QUENZASESSID'));
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => str_starts_with((string) $this->app->config('app.url', ''), 'https://'),
                'use_strict_mode' => true,
            ]);
        }

        $_SESSION[self::CSRF_SESSION_KEY] ??= [];
    }

    private function purgeExpiredTokens(): void
    {
        $_SESSION[self::CSRF_SESSION_KEY] ??= [];

        foreach ($_SESSION[self::CSRF_SESSION_KEY] as $key => $tokenData) {
            if (!is_array($tokenData)) {
                unset($_SESSION[self::CSRF_SESSION_KEY][$key]);

                continue;
            }

            $expiresAt = (int) ($tokenData['expires_at'] ?? 0);

            if ($expiresAt <= time()) {
                unset($_SESSION[self::CSRF_SESSION_KEY][$key]);
            }
        }
    }
}
