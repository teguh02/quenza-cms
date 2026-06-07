<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\View\TwigRenderer;

final class DashboardController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly AuthManager $auth,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/dashboard.twig', [
            'page_title' => trans('common.dashboard'),
            'user' => $this->auth->user(),
            'role_slugs' => $this->auth->roles(),
            'stats' => [
                'users' => $this->database->table('users')->count(),
                'published_posts' => $this->database->table('posts')->where('status', 'published')->count(),
                'draft_posts' => $this->database->table('posts')->where('status', 'draft')->count(),
                'menus' => $this->database->table('menus')->count(),
            ],
            'recent_posts' => $this->database->table('posts')->orderBy('created_at', 'desc')->limit(5)->get(),
        ]));
    }
}
