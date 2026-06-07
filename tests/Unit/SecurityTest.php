<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\TestCase;

final class SecurityTest extends TestCase
{
    public function test_password_can_be_hashed_and_verified(): void
    {
        $hash = $this->security()->hashPassword('Testing123!');

        self::assertNotSame('Testing123!', $hash);
        self::assertTrue($this->security()->verifyPassword('Testing123!', $hash));
        self::assertFalse($this->security()->verifyPassword('WrongPassword!', $hash));
    }

    public function test_sanitize_email_returns_null_for_invalid_email(): void
    {
        self::assertNull($this->security()->sanitizeEmail('bukan-email'));
        self::assertSame('valid@example.com', $this->security()->sanitizeEmail(' valid@example.com '));
    }

    public function test_csrf_token_can_be_generated_and_validated(): void
    {
        $token = $this->security()->generateCsrfToken('unit-test');

        self::assertTrue($this->security()->validateCsrfToken($token, 'unit-test'));
        self::assertFalse($this->security()->validateCsrfToken($token, 'unit-test'));
    }
}
