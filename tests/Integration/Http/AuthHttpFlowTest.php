<?php
declare(strict_types=1);

namespace Tests\Integration\Http;

use Quenza\Core\Security\Security;
use Tests\Support\DatabaseTestCase;
use Tests\Support\HttpTestClient;

final class AuthHttpFlowTest extends DatabaseTestCase
{
    private HttpTestClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new HttpTestClient($this->kernel());
    }

    public function test_guest_can_register_and_access_dashboard(): void
    {
        $token = $this->client->csrfToken('/register');
        $response = $this->client->post('/register', [
            '_token' => $token,
            'full_name' => 'HTTP Tester',
            'email' => 'http@example.com',
            'password' => 'Testing123!',
            'password_confirmation' => 'Testing123!',
        ]);

        self::assertSame(302, $response->status());
        self::assertSame('/admin', $response->header('Location'));

        $dashboard = $this->client->get('/admin');

        self::assertSame(200, $dashboard->status());
        self::assertStringContainsString('HTTP Tester', $dashboard->content());
        self::assertStringContainsString('Quick Draft', $dashboard->content());
    }

    public function test_guest_is_redirected_to_login_when_accessing_admin(): void
    {
        $response = $this->client->get('/admin');

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->header('Location'));
    }

    public function test_authenticated_user_can_logout_via_http_flow(): void
    {
        /** @var Security $security */
        $security = $this->app->get(Security::class);

        $userId = $this->database()->insertGetId('users', [
            'username' => 'logout_tester',
            'full_name' => 'Logout Tester',
            'email' => 'logout@example.com',
            'password_hash' => $security->hashPassword('Testing123!'),
            'locale' => 'id',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->session()->setAuthUserId($userId);
        $dashboard = $this->client->get('/admin');
        $logoutToken = $this->client->extractCsrfToken($dashboard->content());

        $response = $this->client->post('/logout', ['_token' => $logoutToken]);

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->header('Location'));
        self::assertNull($this->session()->authUserId());
    }
}
