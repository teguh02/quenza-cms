<?php
declare(strict_types=1);

namespace Tests\Unit;

use Quenza\Core\Session\SessionManager;
use Tests\Support\TestCase;

final class SessionManagerTest extends TestCase
{
    public function test_flash_data_is_available_for_next_request_cycle(): void
    {
        /** @var SessionManager $session */
        $session = $this->session();
        $session->start();
        $session->flash('status', 'hello');

        $nextRequestSession = new SessionManager($this->app);
        $nextRequestSession->start();

        self::assertSame('hello', $nextRequestSession->get('status'));

        $thirdRequestSession = new SessionManager($this->app);
        $thirdRequestSession->start();

        self::assertNull($thirdRequestSession->get('status'));
    }

    public function test_auth_user_id_can_be_stored_and_cleared(): void
    {
        $session = $this->session();
        $session->setAuthUserId(99);

        self::assertSame(99, $session->authUserId());

        $session->forgetAuthUserId();

        self::assertNull($session->authUserId());
    }
}
