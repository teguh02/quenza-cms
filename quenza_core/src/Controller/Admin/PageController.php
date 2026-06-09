<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use DateTimeImmutable;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Support\Str;
use Quenza\Core\View\TwigRenderer;

final class PageController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly AuthManager $auth,
        private readonly Security $security,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $pages = $this->database->table('posts')
            ->where('post_type', 'page')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $userIds = array_unique(array_column($pages, 'author_id'));
        $authors = [];
        if (!empty($userIds)) {
            $users = $this->database->table('users')->whereIn('id', $userIds)->get();
            foreach ($users as $u) {
                $authors[$u['id']] = $u['full_name'];
            }
        }
        
        foreach ($pages as &$page) {
            $page['author_name'] = $authors[$page['author_id']] ?? 'Unknown';
        }

        return Response::html($this->view->render('admin/pages/index.twig', [
            'page_title' => 'Pages',
            'pages' => $pages,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('admin/pages/form.twig', [
            'page_title' => 'Create New Page',
            'action_url' => '/admin/pages/store',
            'page' => null,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        $status = $request->input('status', 'draft');

        if ($title === '') {
            $this->session->flash('error', 'Title is required.');
            return Response::redirect('/admin/pages/create');
        }

        $userId = $this->auth->id();
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $slugBase = Str::slug($title);
        $slug = $slugBase;
        
        $count = 1;
        while ($this->database->table('posts')->where('slug', $slug)->first() !== null) {
            $slug = $slugBase . '-' . $count;
            $count++;
        }

        $this->database->insert('posts', [
            'author_id' => $userId,
            'parent_id' => null,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => Str::excerpt($content, 160),
            'content' => $this->security->sanitizeRichText($content),
            'post_type' => 'page',
            'status' => in_array($status, ['draft', 'published', 'archived']) ? $status : 'draft',
            'published_at' => $status === 'published' ? $timestamp : null,
            'meta_title' => $title,
            'meta_description' => Str::excerpt($content, 155),
            'meta_keywords' => '',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->session->flash('status', 'Page created successfully.');
        return Response::redirect('/admin/pages');
    }

    public function edit(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $page = $this->database->table('posts')->where('id', $id)->where('post_type', 'page')->first();

        if ($page === null) {
            $this->session->flash('error', 'Page not found.');
            return Response::redirect('/admin/pages');
        }

        return Response::html($this->view->render('admin/pages/form.twig', [
            'page_title' => 'Edit Page',
            'action_url' => '/admin/pages/' . $id . '/update',
            'page' => $page,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $page = $this->database->table('posts')->where('id', $id)->where('post_type', 'page')->first();

        if ($page === null) {
            $this->session->flash('error', 'Page not found.');
            return Response::redirect('/admin/pages');
        }

        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        $status = $request->input('status', 'draft');

        if ($title === '') {
            $this->session->flash('error', 'Title is required.');
            return Response::redirect('/admin/pages/' . $id . '/edit');
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $slugBase = Str::slug($title);
        $slug = $slugBase;
        
        $count = 1;
        while (true) {
            $existing = $this->database->table('posts')->where('slug', $slug)->first();
            if ($existing === null || $existing['id'] == $id) {
                break;
            }
            $slug = $slugBase . '-' . $count;
            $count++;
        }

        $this->database->table('posts')->where('id', $id)->update([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => Str::excerpt($content, 160),
            'content' => $this->security->sanitizeRichText($content),
            'status' => in_array($status, ['draft', 'published', 'archived']) ? $status : 'draft',
            'published_at' => ($status === 'published' && $page['published_at'] === null) ? $timestamp : $page['published_at'],
            'updated_at' => $timestamp,
        ]);

        $this->session->flash('status', 'Page updated successfully.');
        return Response::redirect('/admin/pages');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('posts')->where('id', $id)->where('post_type', 'page')->delete();

        $this->session->flash('status', 'Page deleted successfully.');
        return Response::redirect('/admin/pages');
    }
}
