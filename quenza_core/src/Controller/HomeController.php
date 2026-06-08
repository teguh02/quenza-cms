<?php
declare(strict_types=1);

namespace Quenza\Core\Controller;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\View\TwigRenderer;

final class HomeController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly OptionService $options,
    ) {
    }

    public function index(Request $request): Response
    {
        $searchQuery = trim((string) $request->input('q', ''));
        $categorySlug = trim((string) $request->input('category', ''));

        $posts = $this->publishedPosts($searchQuery, $categorySlug);
        $categories = $this->categories();
        $recentPosts = $this->recentPosts();
        $aboutPage = $this->publishedPageByPreferredSlug();
        $siteTitle = (string) $this->options->get('site_title', config('app.name', 'Quenza CMS'));

        return Response::html($this->view->render('home.twig', [
            'page_title' => $siteTitle,
            'site_title' => $siteTitle,
            'published_posts' => $posts,
            'categories' => $categories,
            'recent_posts' => $recentPosts,
            'search_query' => $searchQuery,
            'selected_category' => $categorySlug,
            'about_page' => $aboutPage,
        ]));
    }

    public function article(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        $post = $this->database->table('posts')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('post_type', 'post')
            ->first();

        if ($post === null) {
            return Response::notFound('<h1>404</h1><p>Artikel tidak ditemukan.</p>');
        }

        return Response::html($this->view->render('public/article.twig', [
            'page_title' => (string) ($post['meta_title'] ?? $post['title']),
            'post' => $post,
            'recent_posts' => $this->recentPosts(),
        ]));
    }

    public function page(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        $page = $this->database->table('posts')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('post_type', 'page')
            ->first();

        if ($page === null) {
            return Response::notFound('<h1>404</h1><p>Halaman tidak ditemukan.</p>');
        }

        return Response::html($this->view->render('public/page.twig', [
            'page_title' => (string) ($page['meta_title'] ?? $page['title']),
            'page' => $page,
            'recent_posts' => $this->recentPosts(),
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedPosts(string $searchQuery, string $categorySlug): array
    {
        $sql = sprintf('SELECT DISTINCT p.* FROM %s p', $this->database->quotedTable('posts'));
        $conditions = ['p.status = :status', 'p.post_type = :post_type'];
        $bindings = [
            'status' => 'published',
            'post_type' => 'post',
        ];

        if ($categorySlug !== '') {
            $sql .= sprintf(
                ' INNER JOIN %s pc ON pc.post_id = p.id INNER JOIN %s c ON c.id = pc.category_id',
                $this->database->quotedTable('post_categories'),
                $this->database->quotedTable('categories'),
            );
            $conditions[] = 'c.slug = :category_slug';
            $bindings['category_slug'] = $categorySlug;
        }

        if ($searchQuery !== '') {
            $conditions[] = '(p.title LIKE :search OR COALESCE(p.excerpt, \'\') LIKE :search OR COALESCE(p.content, \'\') LIKE :search)';
            $bindings['search'] = '%' . $searchQuery . '%';
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT 12';

        return $this->database->select($sql, $bindings);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        return $this->database->select(sprintf(
            'SELECT c.id, c.name, c.slug, COUNT(p.id) AS post_count
             FROM %s c
             LEFT JOIN %s pc ON pc.category_id = c.id
             LEFT JOIN %s p ON p.id = pc.post_id AND p.status = :status AND p.post_type = :post_type
             GROUP BY c.id, c.name, c.slug
             ORDER BY c.name ASC',
            $this->database->quotedTable('categories'),
            $this->database->quotedTable('post_categories'),
            $this->database->quotedTable('posts'),
        ), [
            'status' => 'published',
            'post_type' => 'post',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPosts(): array
    {
        return $this->database->table('posts')
            ->where('status', 'published')
            ->where('post_type', 'post')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedPageByPreferredSlug(): ?array
    {
        foreach (['tentang-kami', 'about-us'] as $slug) {
            $page = $this->database->table('posts')
                ->where('slug', $slug)
                ->where('status', 'published')
                ->where('post_type', 'page')
                ->first();

            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }
}
