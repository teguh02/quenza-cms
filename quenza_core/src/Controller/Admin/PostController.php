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

final class PostController
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
        $posts = $this->database->table('posts')
            ->where('post_type', 'post')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $userIds = array_unique(array_column($posts, 'author_id'));
        $authors = [];
        if (!empty($userIds)) {
            $users = $this->database->table('users')->whereIn('id', $userIds)->get();
            foreach ($users as $u) {
                $authors[$u['id']] = $u['full_name'];
            }
        }
        
        foreach ($posts as &$post) {
            $post['author_name'] = $authors[$post['author_id']] ?? 'Unknown';
        }

        return Response::html($this->view->render('admin/posts/index.twig', [
            'page_title' => 'Posts',
            'posts' => $posts,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        $categories = $this->database->table('categories')->get();

        return Response::html($this->view->render('admin/posts/form.twig', [
            'page_title' => 'Create New Post',
            'action_url' => '/admin/posts/store',
            'categories' => $categories,
            'post' => null,
            'selected_categories' => [],
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        $categoryId = (int) $request->input('category_id', 0);
        $status = $request->input('status', 'draft');

        if ($title === '') {
            $this->session->flash('error', 'Title is required.');
            return Response::redirect('/admin/posts/create');
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

        $postId = $this->database->insertGetId('posts', [
            'author_id' => $userId,
            'parent_id' => null,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => Str::excerpt($content, 160),
            'content' => $this->security->sanitizeRichText($content),
            'post_type' => 'post',
            'status' => in_array($status, ['draft', 'published', 'archived']) ? $status : 'draft',
            'published_at' => $status === 'published' ? $timestamp : null,
            'meta_title' => $title,
            'meta_description' => Str::excerpt($content, 155),
            'meta_keywords' => '',
            'og_image' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($categoryId > 0) {
            $this->database->insertOrIgnore('post_categories', [
                'post_id' => $postId,
                'category_id' => $categoryId,
            ]);
        }

        $this->session->flash('status', 'Post created successfully.');
        return Response::redirect('/admin/posts');
    }

    public function edit(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $post = $this->database->table('posts')->where('id', $id)->where('post_type', 'post')->first();

        if ($post === null) {
            $this->session->flash('error', 'Post not found.');
            return Response::redirect('/admin/posts');
        }

        $categories = $this->database->table('categories')->get();
        $postCategories = $this->database->table('post_categories')->where('post_id', $id)->get();
        $selectedCategories = array_column($postCategories, 'category_id');

        return Response::html($this->view->render('admin/posts/form.twig', [
            'page_title' => 'Edit Post',
            'action_url' => '/admin/posts/' . $id . '/update',
            'categories' => $categories,
            'post' => $post,
            'selected_categories' => $selectedCategories,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $post = $this->database->table('posts')->where('id', $id)->where('post_type', 'post')->first();

        if ($post === null) {
            $this->session->flash('error', 'Post not found.');
            return Response::redirect('/admin/posts');
        }

        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        $categoryId = (int) $request->input('category_id', 0);
        $status = $request->input('status', 'draft');

        if ($title === '') {
            $this->session->flash('error', 'Title is required.');
            return Response::redirect('/admin/posts/' . $id . '/edit');
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
            'published_at' => ($status === 'published' && $post['published_at'] === null) ? $timestamp : $post['published_at'],
            'updated_at' => $timestamp,
        ]);

        $this->database->table('post_categories')->where('post_id', $id)->delete();
        if ($categoryId > 0) {
            $this->database->insertOrIgnore('post_categories', [
                'post_id' => $id,
                'category_id' => $categoryId,
            ]);
        }

        $this->session->flash('status', 'Post updated successfully.');
        return Response::redirect('/admin/posts');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('posts')->where('id', $id)->where('post_type', 'post')->delete();
        $this->database->table('post_categories')->where('post_id', $id)->delete();

        $this->session->flash('status', 'Post deleted successfully.');
        return Response::redirect('/admin/posts');
    }
}
