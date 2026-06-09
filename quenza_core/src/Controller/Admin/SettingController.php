<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class SettingController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly OptionService $options,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin/settings/index.twig', [
            'page_title' => 'Pengaturan Situs',
            'site_name' => $this->options->get('site_name', 'Quenza CMS'),
            'site_description' => $this->options->get('site_description', 'Just another Quenza site'),
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function update(Request $request): Response
    {
        $siteName = trim((string) $request->input('site_name', ''));
        $siteDescription = trim((string) $request->input('site_description', ''));

        if ($siteName === '') {
            $this->session->flash('error', 'Nama situs tidak boleh kosong.');
            return Response::redirect('/admin/settings');
        }

        $this->options->set('site_name', $siteName);
        $this->options->set('site_description', $siteDescription);

        $this->session->flash('status', 'Pengaturan berhasil disimpan.');
        return Response::redirect('/admin/settings');
    }
}
