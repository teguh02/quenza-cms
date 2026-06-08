<?php
declare(strict_types=1);

namespace Quenza\Core\Auth;

use DateTimeImmutable;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Enums\UserStatus;
use Quenza\Core\Security\Security;
use Quenza\Core\Support\Str;

final class RegistrationService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Security $security,
        private readonly AuthManager $auth,
        private readonly RateLimiterService $rateLimiter,
    ) {
    }

    public function register(string $fullName, string $email, string $password, string $passwordConfirmation, string $ipAddress): AuthResult
    {
        $normalizedName = trim($fullName);
        $normalizedEmail = mb_strtolower(trim($email));
        $errors = [];

        if ($normalizedName === '') {
            $errors['full_name'] = trans('auth.validation.full_name_required');
        }

        $sanitizedEmail = $this->security->sanitizeEmail($normalizedEmail);

        if ($sanitizedEmail === null) {
            $errors['email'] = trans('auth.validation.email_invalid');
        }

        if (strlen($password) < 8) {
            $errors['password'] = trans('auth.validation.password_min');
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = trans('auth.validation.password_confirmation');
        }

        if ($this->rateLimiter->lockedUntil('register', $ipAddress, $normalizedEmail) !== null) {
            return AuthResult::failure(trans('auth.register_throttle'), ['email' => trans('auth.register_throttle')]);
        }

        if ($sanitizedEmail !== null && $this->database->table('users')->where('email', $sanitizedEmail)->exists()) {
            $errors['email'] = trans('auth.validation.email_taken');
        }

        if ($errors !== []) {
            $this->rateLimiter->hit('register', $ipAddress, $normalizedEmail);

            return AuthResult::failure(trans('auth.register_failed'), $errors);
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $username = $this->generateUniqueUsername($normalizedName, $sanitizedEmail ?? $normalizedEmail);
        $userId = $this->database->insertGetId('users', [
            'username' => $username,
            'full_name' => $normalizedName,
            'email' => $sanitizedEmail,
            'password_hash' => $this->security->hashPassword($password),
            'locale' => (string) config('app.locale', 'id'),
            'status' => UserStatus::Active->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subscriberRole = $this->database->table('roles')->where('slug', 'subscriber')->first();

        if ($subscriberRole !== null && is_numeric($subscriberRole['id'] ?? null)) {
            $this->database->insertOrIgnore('user_roles', [
                'user_id' => $userId,
                'role_id' => (int) $subscriberRole['id'],
                'assigned_at' => $now,
            ]);
        }

        $this->rateLimiter->clear('register', $ipAddress, $normalizedEmail);
        $this->auth->loginById($userId);

        return AuthResult::success(trans('auth.register_success'));
    }

    private function generateUniqueUsername(string $fullName, string $email): string
    {
        $seed = $fullName !== '' ? $fullName : ((strstr($email, '@', true)) ?: 'user');
        $base = substr(Str::slug($seed, '_'), 0, 40);

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->database->table('users')->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = substr($base, 0, max(1, 40 - strlen((string) $suffix) - 1)) . '_' . $suffix;
        }

        return $candidate;
    }
}
