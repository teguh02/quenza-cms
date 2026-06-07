<?php
declare(strict_types=1);

namespace Tests\Integration\Auth;

use Quenza\Core\Auth\RegistrationService;
use Tests\Support\DatabaseTestCase;

final class RegistrationServiceTest extends DatabaseTestCase
{
    public function test_public_registration_creates_active_user_and_subscriber_role(): void
    {
        /** @var RegistrationService $service */
        $service = $this->app->get(RegistrationService::class);

        $result = $service->register('Public Tester', 'public@example.com', 'Testing123!', 'Testing123!', '127.0.0.1');

        self::assertTrue($result->successful);

        $user = $this->database()->table('users')->where('email', 'public@example.com')->first();
        self::assertNotNull($user);
        self::assertSame('active', $user['status']);
        self::assertNotSame('Testing123!', $user['password_hash']);

        $roles = $this->database()->select(
            sprintf(
                'SELECT r.slug FROM %s ur INNER JOIN %s r ON r.id = ur.role_id WHERE ur.user_id = :user_id',
                $this->database()->quotedTable('user_roles'),
                $this->database()->quotedTable('roles'),
            ),
            ['user_id' => (int) $user['id']],
        );

        self::assertSame('subscriber', $roles[0]['slug']);
    }

    public function test_registration_fails_for_duplicate_email(): void
    {
        /** @var RegistrationService $service */
        $service = $this->app->get(RegistrationService::class);

        $service->register('Public Tester', 'public@example.com', 'Testing123!', 'Testing123!', '127.0.0.1');
        $result = $service->register('Public Tester 2', 'public@example.com', 'Testing123!', 'Testing123!', '127.0.0.1');

        self::assertFalse($result->successful);
        self::assertArrayHasKey('email', $result->errors);
    }
}
