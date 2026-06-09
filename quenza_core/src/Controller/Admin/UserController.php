<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use DateTimeImmutable;
use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class UserController
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
        $users = $this->database->table('users')->orderBy('full_name', 'asc')->get();

        return Response::html($this->view->render('admin/users/index.twig', [
            'page_title' => 'Pengguna',
            'users' => $users,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function create(Request $request): Response
    {
        return Response::html($this->view->render('admin/users/form.twig', [
            'page_title' => 'Tambah Pengguna Baru',
            'action_url' => '/admin/users/store',
            'user' => null,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function store(Request $request): Response
    {
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $fullName = trim((string) $request->input('full_name', ''));
        $password = (string) $request->input('password', '');
        $isActive = (int) $request->input('is_active', 1);

        if ($username === '' || $email === '' || $password === '') {
            $this->session->flash('error', 'Username, email, dan password wajib diisi.');
            return Response::redirect('/admin/users/create');
        }

        // Check unique username
        if ($this->database->table('users')->where('username', $username)->first()) {
            $this->session->flash('error', 'Username sudah digunakan.');
            return Response::redirect('/admin/users/create');
        }

        // Check unique email
        if ($this->database->table('users')->where('email', $email)->first()) {
            $this->session->flash('error', 'Email sudah digunakan.');
            return Response::redirect('/admin/users/create');
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->database->insert('users', [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'is_active' => $isActive,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->session->flash('status', 'Pengguna berhasil ditambahkan.');
        return Response::redirect('/admin/users');
    }

    public function edit(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $user = $this->database->table('users')->where('id', $id)->first();

        if ($user === null) {
            $this->session->flash('error', 'Pengguna tidak ditemukan.');
            return Response::redirect('/admin/users');
        }

        return Response::html($this->view->render('admin/users/form.twig', [
            'page_title' => 'Edit Pengguna',
            'action_url' => '/admin/users/' . $id . '/update',
            'user' => $user,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $user = $this->database->table('users')->where('id', $id)->first();

        if ($user === null) {
            $this->session->flash('error', 'Pengguna tidak ditemukan.');
            return Response::redirect('/admin/users');
        }

        $fullName = trim((string) $request->input('full_name', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $isActive = (int) $request->input('is_active', 1);

        if ($email === '') {
            $this->session->flash('error', 'Email wajib diisi.');
            return Response::redirect('/admin/users/' . $id . '/edit');
        }

        // Check unique email excluding current user
        $existingEmail = $this->database->table('users')->where('email', $email)->first();
        if ($existingEmail && $existingEmail['id'] !== $id) {
            $this->session->flash('error', 'Email sudah digunakan pengguna lain.');
            return Response::redirect('/admin/users/' . $id . '/edit');
        }

        $updateData = [
            'full_name' => $fullName,
            'email' => $email,
            'is_active' => $isActive,
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($password !== '') {
            $updateData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->database->table('users')->where('id', $id)->update($updateData);

        $this->session->flash('status', 'Data pengguna berhasil diupdate.');
        return Response::redirect('/admin/users');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        
        if ($id === $this->auth->id()) {
            $this->session->flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
            return Response::redirect('/admin/users');
        }

        $this->database->table('users')->where('id', $id)->delete();
        $this->database->table('user_roles')->where('user_id', $id)->delete();

        $this->session->flash('status', 'Pengguna berhasil dihapus.');
        return Response::redirect('/admin/users');
    }
}
