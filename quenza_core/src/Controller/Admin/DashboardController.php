<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use DateTimeImmutable;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Cms\ActivityLogService;
use Quenza\Core\Cms\OptionService;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Support\Str;
use Quenza\Core\View\TwigRenderer;

final class DashboardController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly AuthManager $auth,
        private readonly ActivityLogService $activity,
        private readonly OptionService $options,
        private readonly Security $security,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/dashboard.twig', [
            'page_title' => trans('common.dashboard'),
            'user' => $this->auth->user(),
            'role_slugs' => $this->auth->roles(),
            'site_title' => (string) $this->options->get('site_title', config('app.name', 'Quenza CMS')),
            'stats' => [
                'posts' => $this->database->table('posts')->where('post_type', 'post')->count(),
                'pages' => $this->database->table('posts')->where('post_type', 'page')->count(),
                'categories' => $this->database->table('categories')->count(),
                'media' => $this->database->table('media')->count(),
            ],
            'recent_posts' => $this->database->table('posts')->orderBy('created_at', 'desc')->limit(5)->get(),
            'recent_activity' => $this->activity->recent(6),
        ]));
    }

    public function quickDraft(Request $request): Response
    {
        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));

        if ($title === '') {
            return Response::redirect('/admin');
        }

        $userId = $this->auth->id();
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $slugBase = Str::slug($title);
        $slug = $slugBase . '-' . time();

        $postId = $this->database->insertGetId('posts', [
            'author_id' => $userId,
            'parent_id' => null,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => Str::excerpt($content, 160),
            'content' => $this->security->sanitizeRichText($content),
            'post_type' => 'post',
            'status' => 'draft',
            'published_at' => null,
            'meta_title' => $title,
            'meta_description' => Str::excerpt($content, 155),
            'meta_keywords' => 'draft,quenza,cms',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $uncategorized = $this->database->table('categories')->where('slug', 'uncategorized')->first();

        if ($uncategorized !== null) {
            $this->database->insertOrIgnore('post_categories', [
                'post_id' => $postId,
                'category_id' => (int) $uncategorized['id'],
            ]);
        }

        $this->activity->log('post.quick_draft', sprintf('Quick draft "%s" dibuat.', $title), $userId, 'post', $postId);
        $this->session->flash('status', 'Quick draft berhasil disimpan.');

        return Response::redirect('/admin');
    }
}
