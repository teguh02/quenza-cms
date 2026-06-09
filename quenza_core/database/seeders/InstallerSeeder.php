<?php
declare(strict_types=1);

namespace Database\Seeders;

use DateInterval;
use DateTimeImmutable;
use Quenza\Core\Cms\ActivityLogService;
use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\Connection;
use Quenza\Core\Database\Seeder;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Security\Security;
use Quenza\Core\Support\Str;

final class InstallerSeeder extends Seeder
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(Connection $connection, Application $app, private readonly array $payload = [])
    {
        parent::__construct($connection, $app);
    }

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        /** @var Security $security */
        $security = $this->app->get(Security::class);

        /** @var OptionService $options */
        $options = $this->app->get(OptionService::class);

        /** @var ActivityLogService $activity */
        $activity = $this->app->get(ActivityLogService::class);

        $locale = (string) ($this->payload['locale'] ?? 'id');
        $siteTitle = (string) ($this->payload['site_title'] ?? 'Quenza CMS');
        $siteUrl = (string) ($this->payload['site_url'] ?? 'http://localhost');
        $adminUsername = (string) ($this->payload['admin_username'] ?? 'admin');
        $adminEmail = (string) ($this->payload['admin_email'] ?? 'admin@example.com');
        $adminPassword = (string) ($this->payload['admin_password'] ?? 'ChangeMe123!');
        $now = new DateTimeImmutable();
        $timestamp = $now->format('Y-m-d H:i:s');

        $options->set('site_title', $siteTitle);
        $options->set('site_name', $siteTitle);
        $options->set('site_url', $siteUrl);
        $options->set('active_theme', 'quenza_default');
        $options->set('active_locale', $locale);
        $options->set('site_locale', $locale);
        $options->set('installation_completed_at', $timestamp);

        $adminUserId = $this->db()->insertGetId('users', [
            'username' => $adminUsername,
            'full_name' => $adminUsername,
            'email' => $adminEmail,
            'password_hash' => $security->hashPassword($adminPassword),
            'locale' => $locale,
            'status' => 'active',
            'failed_login_attempts' => 0,
            'last_login_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $adminRole = $this->db()->table('roles')->where('slug', 'admin')->first();

        if ($adminRole !== null) {
            $this->db()->insertOrIgnore('user_roles', [
                'user_id' => $adminUserId,
                'role_id' => (int) $adminRole['id'],
                'assigned_at' => $timestamp,
            ]);
        }

        $categoryIds = $this->seedCategories($timestamp);
        $this->seedPagesAndPosts($locale, $adminUserId, $categoryIds, $security, $timestamp);

        $activity->log('install.completed', sprintf('Instalasi awal Quenza CMS selesai untuk situs %s.', $siteTitle), $adminUserId, 'installation', null);
        $activity->log('user.created', sprintf('Akun admin %s dibuat saat instalasi.', $adminUsername), $adminUserId, 'user', $adminUserId);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(string $timestamp): array
    {
        $categories = [
            ['name' => 'Uncategorized', 'slug' => 'uncategorized', 'description' => 'Kategori bawaan untuk artikel yang belum diklasifikasikan.'],
        ];

        $ids = [];

        foreach ($categories as $category) {
            $categoryId = $this->db()->insertGetId('categories', [
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $ids[$category['slug']] = $categoryId;
        }

        return $ids;
    }

    /**
     * @param array<string, int> $categoryIds
     */
    private function seedPagesAndPosts(string $locale, int $adminUserId, array $categoryIds, Security $security, string $timestamp): void
    {
        $samplePageContent = '<p>This is an example page. It\'s different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors.</p>';

        $this->db()->insert('posts', [
            'author_id' => $adminUserId,
            'parent_id' => null,
            'title' => 'Sample Page',
            'slug' => 'sample-page',
            'excerpt' => Str::excerpt($samplePageContent, 140),
            'content' => $security->sanitizeRichText($samplePageContent),
            'post_type' => 'page',
            'status' => 'published',
            'published_at' => $timestamp,
            'meta_title' => 'Sample Page',
            'meta_description' => 'An example page.',
            'meta_keywords' => 'sample,page',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $helloWorldContent = '<p>Welcome to Quenza CMS. This is your first post. Edit or delete it, then start writing! You can delete this post and create your own content.</p>';

        $postId = $this->db()->insertGetId('posts', [
            'author_id' => $adminUserId,
            'parent_id' => null,
            'title' => 'Hello World!',
            'slug' => 'hello-world',
            'excerpt' => Str::excerpt($helloWorldContent, 160),
            'content' => $security->sanitizeRichText($helloWorldContent),
            'post_type' => 'post',
            'status' => 'published',
            'published_at' => $timestamp,
            'meta_title' => 'Hello World!',
            'meta_description' => 'Welcome to Quenza CMS.',
            'meta_keywords' => 'hello,world,quenza',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->db()->insertOrIgnore('post_categories', [
            'post_id' => $postId,
            'category_id' => $categoryIds['uncategorized'],
        ]);
    }
}
