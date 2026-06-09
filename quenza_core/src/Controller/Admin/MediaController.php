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

final class MediaController
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
        $media = $this->database->table('media')->orderBy('created_at', 'desc')->get();

        return Response::html($this->view->render('admin/media/index.twig', [
            'page_title' => 'Media Library',
            'media' => $media,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function upload(Request $request): Response
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->session->flash('error', 'Gagal mengupload file.');
            return Response::redirect('/admin/media');
        }

        $file = $_FILES['file'];
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($file['name']));
        
        $uploadDir = dirname(__DIR__, 4) . '/public/uploads/media';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $destination = $uploadDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $mimeType = mime_content_type($destination) ?: 'application/octet-stream';
            $size = filesize($destination);
            
            $width = null;
            $height = null;
            if (str_starts_with($mimeType, 'image/')) {
                $info = @getimagesize($destination);
                if ($info) {
                    $width = $info[0];
                    $height = $info[1];
                }
            }

            $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->database->insert('media', [
                'uploader_user_id' => $this->auth->id(),
                'disk' => 'local',
                'path' => '/uploads/media/' . $filename,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'width' => $width,
                'height' => $height,
                'alt_text' => '',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $this->session->flash('status', 'File berhasil diupload.');
        } else {
            $this->session->flash('error', 'Gagal memindahkan file.');
        }

        return Response::redirect('/admin/media');
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $media = $this->database->table('media')->where('id', $id)->first();

        if ($media) {
            $filePath = dirname(__DIR__, 4) . '/public' . $media['path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $this->database->table('media')->where('id', $id)->delete();
            $this->session->flash('status', 'Media berhasil dihapus.');
        }

        return Response::redirect('/admin/media');
    }
}
