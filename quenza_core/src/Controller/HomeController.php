<?php
declare(strict_types=1);

namespace Quenza\Core\Controller;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\View\TwigRenderer;

final class HomeController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly AuthManager $auth,
    ) {
    }

    public function index(Request $request): Response
    {
        $publishedPosts = $this->database->table('posts')
            ->where('status', 'published')
            ->where('post_type', 'post')
            ->orderBy('published_at', 'desc')
            ->limit(6)
            ->get();

        return Response::html($this->view->render('home.twig', [
            'page_title' => trans('app.name'),
            'published_posts' => $publishedPosts,
            'is_authenticated' => $this->auth->check(),
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
        ]));
    }
}
