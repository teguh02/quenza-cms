<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use DateTimeImmutable;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Support\Str;
use Quenza\Core\View\TwigRenderer;

final class CategoryController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly AuthManager $auth,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $categories = $this->database->table('categories')->orderBy('name', 'asc')->get();
        
        // Count posts per category
        $counts = [];
        $postCats = $this->database->table('post_categories')->get();
        foreach ($postCats as $pc) {
            $counts[$pc['category_id']] = ($counts[$pc['category_id']] ?? 0) + 1;
        }

        foreach ($categories as &$cat) {
            $cat['post_count'] = $counts[$cat['id']] ?? 0;
        }

        return Response::html($this->view->render('admin/categories/index.twig', [
            'page_title' => 'Kategori',
            'categories' => $categories,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('admin/categories/form.twig', [
            'page_title' => 'Buat Kategori Baru',
            'action_url' => '/admin/categories/store',
            'category' => null,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));

        if ($name === '') {
            $this->session->flash('error', 'Nama kategori wajib diisi.');
            return Response::redirect('/admin/categories/create');
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $slugBase = Str::slug($name);
        $slug = $slugBase;
        
        $count = 1;
        while ($this->database->table('categories')->where('slug', $slug)->first() !== null) {
            $slug = $slugBase . '-' . $count;
            $count++;
        }

        $this->database->insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->session->flash('status', 'Kategori berhasil dibuat.');
        return Response::redirect('/admin/categories');
    }

    public function edit(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $category = $this->database->table('categories')->where('id', $id)->first();

        if ($category === null) {
            $this->session->flash('error', 'Kategori tidak ditemukan.');
            return Response::redirect('/admin/categories');
        }

        return Response::html($this->view->render('admin/categories/form.twig', [
            'page_title' => 'Edit Kategori',
            'action_url' => '/admin/categories/' . $id . '/update',
            'category' => $category,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $category = $this->database->table('categories')->where('id', $id)->first();

        if ($category === null) {
            $this->session->flash('error', 'Kategori tidak ditemukan.');
            return Response::redirect('/admin/categories');
        }

        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));

        if ($name === '') {
            $this->session->flash('error', 'Nama kategori wajib diisi.');
            return Response::redirect('/admin/categories/' . $id . '/edit');
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $slugBase = Str::slug($name);
        $slug = $slugBase;
        
        $count = 1;
        while (true) {
            $existing = $this->database->table('categories')->where('slug', $slug)->first();
            if ($existing === null || $existing['id'] == $id) {
                break;
            }
            $slug = $slugBase . '-' . $count;
            $count++;
        }

        $this->database->table('categories')->where('id', $id)->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'updated_at' => $timestamp,
        ]);

        $this->session->flash('status', 'Kategori berhasil diupdate.');
        return Response::redirect('/admin/categories');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        
        // Cek jika ini kategori Uncategorized (biasanya slug uncategorized)
        $cat = $this->database->table('categories')->where('id', $id)->first();
        if ($cat && $cat['slug'] === 'uncategorized') {
            $this->session->flash('error', 'Kategori bawaan (Uncategorized) tidak boleh dihapus.');
            return Response::redirect('/admin/categories');
        }

        $this->database->table('categories')->where('id', $id)->delete();
        $this->database->table('post_categories')->where('category_id', $id)->delete();

        $this->session->flash('status', 'Kategori berhasil dihapus.');
        return Response::redirect('/admin/categories');
    }
}
