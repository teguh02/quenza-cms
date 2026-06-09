<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class CommentController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $database,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $comments = $this->database->table('comments')->orderBy('created_at', 'desc')->get();
        
        $postIds = array_unique(array_column($comments, 'post_id'));
        $posts = [];
        if (!empty($postIds)) {
            $postsData = $this->database->table('posts')->whereIn('id', $postIds)->get();
            foreach ($postsData as $p) {
                $posts[$p['id']] = $p['title'];
            }
        }

        foreach ($comments as &$comment) {
            $comment['post_title'] = $posts[$comment['post_id']] ?? 'Unknown Post';
        }

        return Response::html($this->view->render('admin/comments/index.twig', [
            'page_title' => 'Komentar',
            'comments' => $comments,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function approve(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('comments')->where('id', $id)->update(['status' => 'approved']);
        $this->session->flash('status', 'Komentar disetujui.');
        return Response::redirect('/admin/comments');
    }

    public function unapprove(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('comments')->where('id', $id)->update(['status' => 'pending']);
        $this->session->flash('status', 'Komentar dikembalikan ke pending.');
        return Response::redirect('/admin/comments');
    }

    public function spam(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('comments')->where('id', $id)->update(['status' => 'spam']);
        $this->session->flash('status', 'Komentar ditandai sebagai spam.');
        return Response::redirect('/admin/comments');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $this->database->table('comments')->where('id', $id)->delete();
        $this->session->flash('status', 'Komentar berhasil dihapus permanen.');
        return Response::redirect('/admin/comments');
    }
}
