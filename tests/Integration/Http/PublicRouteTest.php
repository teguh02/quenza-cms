<?php
declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Support\DatabaseTestCase;
use Tests\Support\HttpTestClient;

final class PublicRouteTest extends DatabaseTestCase
{
    private HttpTestClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new HttpTestClient($this->kernel());
    }

    public function test_homepage_renders_successfully(): void
    {
        $response = $this->client->get('/');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Blog Quenza CMS langsung siap menampilkan artikel', $response->content());
    }

    public function test_article_route_renders_published_article_by_slug(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->database()->insert('posts', [
            'title' => 'Artikel Test Route',
            'slug' => 'artikel-test-route',
            'excerpt' => 'Ringkasan artikel untuk test route.',
            'content' => 'Konten publik artikel test route.',
            'post_type' => 'post',
            'status' => 'published',
            'published_at' => $now,
            'meta_title' => 'Artikel Test Route',
            'meta_description' => 'Meta route',
            'meta_keywords' => 'route,test',
            'og_image' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->client->get('/articles/artikel-test-route');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Artikel Test Route', $response->content());
        self::assertStringContainsString('Konten publik artikel test route.', $response->content());
    }
}
