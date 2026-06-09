<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class IdentityController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly OptionService $options,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/appearance/identity.twig', [
            'page_title' => 'Site Identity',
            'site_logo' => $this->options->get('site_logo'),
            'site_favicon' => $this->options->get('site_favicon'),
            'primary_color' => $this->options->get('primary_color', '#14B8A6'),
            'footer_copyright' => $this->options->get('footer_copyright', 'Copyright &copy; Quenza CMS.'),
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request): Response
    {
        $primaryColor = trim((string) $request->input('primary_color', '#14B8A6'));
        $footerCopyright = trim((string) $request->input('footer_copyright', ''));
        
        $this->options->set('primary_color', $primaryColor);
        $this->options->set('footer_copyright', $footerCopyright);

        // Handle File Uploads (Logo & Favicon)
        $uploadDir = realpath(__DIR__ . '/../../../../..') . '/public/uploads/media';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $logoName = 'logo_' . time() . '_' . basename($_FILES['site_logo']['name']);
            $logoPath = $uploadDir . DIRECTORY_SEPARATOR . $logoName;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $logoPath)) {
                $this->options->set('site_logo', '/uploads/media/' . $logoName);
            }
        }

        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $faviconName = 'favicon_' . time() . '_' . basename($_FILES['site_favicon']['name']);
            $faviconPath = $uploadDir . DIRECTORY_SEPARATOR . $faviconName;
            if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $faviconPath)) {
                $this->options->set('site_favicon', '/uploads/media/' . $faviconName);
            }
        }

        // Delete handlers
        if ($request->input('delete_logo') === '1') {
            $this->options->set('site_logo', null);
        }
        if ($request->input('delete_favicon') === '1') {
            $this->options->set('site_favicon', null);
        }

        $this->session->flash('status', 'Identitas situs berhasil diperbarui.');
        return Response::redirect('/admin/identity');
    }
}
