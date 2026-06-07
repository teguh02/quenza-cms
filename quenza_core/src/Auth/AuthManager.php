<?php
declare(strict_types=1);

namespace Quenza\Core\Auth;

use DateInterval;
use DateTimeImmutable;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Enums\UserStatus;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;

final class AuthManager
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedUser = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly SessionManager $session,
        private readonly Security $security,
        private readonly RateLimiterService $rateLimiter,
    ) {
    }

    public function attempt(string $email, string $password, string $ipAddress): AuthResult
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '' || $password === '') {
            return AuthResult::failure(trans('auth.failed'));
        }

        $lockedUntil = $this->rateLimiter->lockedUntil('login', $ipAddress, $normalizedEmail);

        if ($lockedUntil !== null) {
            return AuthResult::failure(trans('auth.throttle')); 
        }

        $user = $this->database->table('users')->where('email', $normalizedEmail)->first();

        if ($this->isUserLocked($user)) {
            return AuthResult::failure(trans('auth.locked'));
        }

        if ($user === null || !$this->security->verifyPassword($password, (string) $user['password_hash'])) {
            $this->rateLimiter->hit('login', $ipAddress, $normalizedEmail);
            $this->recordFailure($user);

            return AuthResult::failure(trans('auth.failed'));
        }

        if (($user['status'] ?? UserStatus::Inactive->value) !== UserStatus::Active->value) {
            return AuthResult::failure(trans('auth.inactive'));
        }

        if ($this->security->needsRehash((string) $user['password_hash'])) {
            $this->database->update('users', [
                'password_hash' => $this->security->hashPassword($password),
                'updated_at' => $this->now(),
            ], [
                'id' => (int) $user['id'],
            ]);
        }

        $this->database->update('users', [
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => $this->now(),
            'updated_at' => $this->now(),
        ], [
            'id' => (int) $user['id'],
        ]);

        $this->rateLimiter->clear('login', $ipAddress, $normalizedEmail);
        $this->loginById((int) $user['id']);

        return AuthResult::success(trans('auth.login_success'));
    }

    public function loginById(int $userId): void
    {
        $this->database->update('users', [
            'last_login_at' => $this->now(),
            'updated_at' => $this->now(),
        ], [
            'id' => $userId,
        ]);

        $this->session->regenerate();
        $this->session->setAuthUserId($userId);
        $this->cachedUser = null;
    }

    public function logout(): void
    {
        $this->session->forgetAuthUserId();
        $this->session->invalidate();
        $this->cachedUser = null;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        return $this->session->authUserId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $userId = $this->id();

        if ($userId === null) {
            return null;
        }

        return $this->cachedUser = $this->database->table('users')->where('id', $userId)->first();
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        $userId = $this->id();

        if ($userId === null) {
            return [];
        }

        $rows = $this->database->select(
            sprintf(
                'SELECT r.slug FROM %s ur INNER JOIN %s r ON r.id = ur.role_id WHERE ur.user_id = :user_id ORDER BY r.slug ASC',
                $this->database->quotedTable('user_roles'),
                $this->database->quotedTable('roles'),
            ),
            ['user_id' => $userId],
        );

        return array_map(static fn (array $row): string => (string) $row['slug'], $rows);
    }

    public function hasRole(array|string $roles): bool
    {
        $requiredRoles = is_array($roles) ? $roles : [$roles];
        $userRoles = $this->roles();

        foreach ($requiredRoles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function recordFailure(?array $user): void
    {
        if ($user === null || !is_numeric($user['id'] ?? null)) {
            return;
        }

        $attempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;
        $lockedUntil = $attempts >= 5
            ? (new DateTimeImmutable())->add(new DateInterval('PT15M'))->format('Y-m-d H:i:s')
            : null;

        $this->database->update('users', [
            'failed_login_attempts' => $attempts,
            'locked_until' => $lockedUntil,
            'updated_at' => $this->now(),
        ], [
            'id' => (int) $user['id'],
        ]);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function isUserLocked(?array $user): bool
    {
        if ($user === null || ($user['locked_until'] ?? null) === null || $user['locked_until'] === '') {
            return false;
        }

        return new DateTimeImmutable((string) $user['locked_until']) > new DateTimeImmutable();
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
