<?php
declare(strict_types=1);

namespace Tests\Integration\Auth;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Security\Security;
use Tests\Support\DatabaseTestCase;

final class AuthManagerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var Security $security */
        $security = $this->app->get(Security::class);

        $this->database()->insert('users', [
            'full_name' => 'Auth Tester',
            'email' => 'auth@example.com',
            'password_hash' => $security->hashPassword('Testing123!'),
            'locale' => 'id',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function test_attempt_logs_user_in_and_resets_failed_counter(): void
    {
        /** @var AuthManager $auth */
        $auth = $this->app->get(AuthManager::class);

        $result = $auth->attempt('auth@example.com', 'Testing123!', '127.0.0.1');

        self::assertTrue($result->successful);
        self::assertTrue($auth->check());

        $user = $this->database()->table('users')->where('email', 'auth@example.com')->first();
        self::assertNotNull($user);
        self::assertSame(0, (int) $user['failed_login_attempts']);
        self::assertNotNull($user['last_login_at']);
    }

    public function test_attempt_locks_user_after_five_failed_logins(): void
    {
        /** @var AuthManager $auth */
        $auth = $this->app->get(AuthManager::class);

        foreach (range(1, 5) as $attempt) {
            $result = $auth->attempt('auth@example.com', 'WrongPassword!', '127.0.0.1');
            self::assertFalse($result->successful);
        }

        $user = $this->database()->table('users')->where('email', 'auth@example.com')->first();
        self::assertNotNull($user);
        self::assertSame(5, (int) $user['failed_login_attempts']);
        self::assertNotNull($user['locked_until']);

        $attempts = $this->database()->table('auth_attempts')->where('action', 'login')->count();
        self::assertSame(1, $attempts);
    }
}
