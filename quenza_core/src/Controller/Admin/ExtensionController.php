<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Packages\ManifestValidator;
use Quenza\Core\Packages\PackageDiscoverer;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class ExtensionController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly OptionService $options,
        private readonly SessionManager $session,
    ) {
    }

    private function getDiscoverer(): PackageDiscoverer
    {
        $basePath = realpath(__DIR__ . '/../../../../..');
        if ($basePath === false) {
            $basePath = __DIR__ . '/../../../../..';
        }
        $validator = new ManifestValidator();
        $activeTheme = (string) $this->options->get('active_theme', 'default');

        return new PackageDiscoverer($basePath, $validator, $activeTheme);
    }

    public function plugins(Request $request): Response
    {
        $discoverer = $this->getDiscoverer();
        $plugins = [];
        try {
            $plugins = $discoverer->discoverPlugins();
        } catch (\Throwable $e) {
            // Log error
        }

        return Response::html($this->view->render('admin/extensions/plugins.twig', [
            'page_title' => 'Plugins',
            'plugins' => $plugins,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function themes(Request $request): Response
    {
        $discoverer = $this->getDiscoverer();
        $themes = [];
        try {
            $themes = $discoverer->discoverThemes(false);
        } catch (\Throwable $e) {
            // Log error
        }

        return Response::html($this->view->render('admin/extensions/themes.twig', [
            'page_title' => 'Themes',
            'themes' => $themes,
            'active_theme' => $this->options->get('active_theme', 'default'),
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }
}
