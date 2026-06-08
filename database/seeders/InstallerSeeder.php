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
            ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Artikel teknologi, produk, dan sistem digital.'],
            ['name' => 'News', 'slug' => 'news', 'description' => 'Kabar terbaru, pengumuman, dan pembaruan situs.'],
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
        $aboutTitle = $locale === 'en' ? 'About Us' : 'Tentang Kami';
        $aboutSlug = $locale === 'en' ? 'about-us' : 'tentang-kami';
        $aboutContent = $locale === 'en'
            ? '<p>Welcome to your new Quenza CMS website. This page is generated automatically so your site is immediately usable after installation.</p><p>You can edit this page from the admin area and replace it with your own story, team profile, or company overview.</p>'
            : '<p>Selamat datang di website Quenza CMS Anda yang baru. Halaman ini dibuat otomatis agar situs langsung terlihat fungsional setelah instalasi selesai.</p><p>Anda dapat mengubah halaman ini dari area admin dan menggantinya dengan cerita, profil tim, atau gambaran organisasi Anda sendiri.</p>';

        $this->db()->insert('posts', [
            'author_id' => $adminUserId,
            'parent_id' => null,
            'title' => $aboutTitle,
            'slug' => $aboutSlug,
            'excerpt' => Str::excerpt($aboutContent, 140),
            'content' => $security->sanitizeRichText($aboutContent),
            'post_type' => 'page',
            'status' => 'published',
            'published_at' => $timestamp,
            'meta_title' => $aboutTitle,
            'meta_description' => Str::excerpt($aboutContent, 150),
            'meta_keywords' => 'about,company,quenza',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $dummyPosts = $locale === 'en'
            ? [
                [
                    'title' => 'Welcome to Your New Quenza Blog',
                    'slug' => 'welcome-to-your-new-quenza-blog',
                    'category' => 'uncategorized',
                    'content' => '<p>This is your very first Quenza blog post. It exists so the frontend immediately looks like a live CMS instead of an empty shell.</p><p>You can edit or delete this post any time from the admin area.</p>',
                ],
                [
                    'title' => 'Technology Stack Ready for Growth',
                    'slug' => 'technology-stack-ready-for-growth',
                    'category' => 'technology',
                    'content' => '<p>Quenza CMS is prepared for SQLite and MySQL, uses a centralized database layer, and ships with secure defaults for content-driven products.</p>',
                ],
                [
                    'title' => 'Latest News From Your Fresh Installation',
                    'slug' => 'latest-news-from-your-fresh-installation',
                    'category' => 'news',
                    'content' => '<p>Your installation is complete. From here, continue by customizing the homepage, adding media, and publishing new articles for your audience.</p>',
                ],
            ]
            : [
                [
                    'title' => 'Selamat Datang di Blog Quenza Anda',
                    'slug' => 'selamat-datang-di-blog-quenza-anda',
                    'category' => 'uncategorized',
                    'content' => '<p>Ini adalah artikel pertama Quenza CMS Anda. Artikel ini dibuat agar frontend langsung terlihat seperti blog yang aktif, bukan situs kosong setelah instalasi.</p><p>Anda dapat mengubah atau menghapus artikel ini kapan saja dari dashboard admin.</p>',
                ],
                [
                    'title' => 'Stack Teknologi Siap Berkembang',
                    'slug' => 'stack-teknologi-siap-berkembang',
                    'category' => 'technology',
                    'content' => '<p>Quenza CMS mendukung SQLite dan MySQL, memakai lapisan database terpusat, serta menyiapkan fondasi yang aman untuk pertumbuhan produk berbasis konten.</p>',
                ],
                [
                    'title' => 'Berita Terbaru Dari Instalasi Baru Anda',
                    'slug' => 'berita-terbaru-dari-instalasi-baru-anda',
                    'category' => 'news',
                    'content' => '<p>Instalasi Anda telah selesai. Langkah berikutnya adalah menyesuaikan beranda, menambah media, dan menerbitkan artikel baru untuk audiens Anda.</p>',
                ],
            ];

        foreach ($dummyPosts as $index => $post) {
            $publishedAt = (new DateTimeImmutable($timestamp))->sub(new DateInterval('P' . max(0, 2 - $index) . 'D'))->format('Y-m-d H:i:s');
            $postId = $this->db()->insertGetId('posts', [
                'author_id' => $adminUserId,
                'parent_id' => null,
                'title' => $post['title'],
                'slug' => $post['slug'],
                'excerpt' => Str::excerpt($post['content'], 160),
                'content' => $security->sanitizeRichText($post['content']),
                'post_type' => 'post',
                'status' => 'published',
                'published_at' => $publishedAt,
                'meta_title' => $post['title'],
                'meta_description' => Str::excerpt($post['content'], 155),
                'meta_keywords' => 'quenza,cms,blog',
                'og_image' => null,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);

            $this->db()->insertOrIgnore('post_categories', [
                'post_id' => $postId,
                'category_id' => $categoryIds[$post['category']],
            ]);
        }
    }
}
