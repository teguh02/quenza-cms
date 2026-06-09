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

    public function activateTheme(Request $request): Response
    {
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $this->session->flash('error', 'Tema tidak valid.');
            return Response::redirect('/admin/appearance');
        }

        $discoverer = $this->getDiscoverer();
        $themes = [];
        try {
            $themes = $discoverer->discoverThemes(false);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Gagal memuat daftar tema.');
            return Response::redirect('/admin/appearance');
        }

        $found = false;
        foreach ($themes as $theme) {
            if ($theme->slug === $slug) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->session->flash('error', 'Tema tidak ditemukan.');
            return Response::redirect('/admin/appearance');
        }

        $this->options->set('active_theme', $slug);
        $this->session->flash('status', 'Tema berhasil diaktifkan.');
        return Response::redirect('/admin/appearance');
    }

    public function deleteTheme(Request $request, array $vars): Response
    {
        $slug = trim($vars['slug'] ?? '');
        $activeTheme = $this->options->get('active_theme', 'quenza_default');

        if ($slug === '' || $slug === $activeTheme) {
            $this->session->flash('error', 'Tema aktif atau default tidak dapat dihapus.');
            return Response::redirect('/admin/appearance');
        }

        $basePath = realpath(__DIR__ . '/../../../../..') ?: __DIR__ . '/../../../../..';
        $themePath = $basePath . DIRECTORY_SEPARATOR . 'qz_content/qz_themes' . DIRECTORY_SEPARATOR . $slug;

        if (is_dir($themePath)) {
            $this->deleteDirectory($themePath);
            $this->session->flash('status', 'Tema berhasil dihapus.');
        } else {
            $this->session->flash('error', 'Tema tidak ditemukan.');
        }

        return Response::redirect('/admin/appearance');
    }

    public function uploadTheme(Request $request): Response
    {
        if (!isset($_FILES['theme_zip']) || $_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
            $this->session->flash('error', 'Gagal mengupload file tema.');
            return Response::redirect('/admin/appearance');
        }

        $file = $_FILES['theme_zip'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            $this->session->flash('error', 'File harus berekstensi .zip');
            return Response::redirect('/admin/appearance');
        }

        $basePath = realpath(__DIR__ . '/../../../../..') ?: __DIR__ . '/../../../../..';
        $themesDir = $basePath . DIRECTORY_SEPARATOR . 'qz_content/qz_themes';

        $zip = new \ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            // Find root folder in zip
            $rootFolder = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (strpos($name, '/') !== false) {
                    $parts = explode('/', $name);
                    if ($rootFolder === '') {
                        $rootFolder = $parts[0];
                    }
                }
            }

            if ($rootFolder === '') {
                $this->session->flash('error', 'Struktur file zip tidak valid.');
                $zip->close();
                return Response::redirect('/admin/appearance');
            }

            // Extract to themes dir
            $zip->extractTo($themesDir);
            $zip->close();

            // Check if theme.json exists
            $themePath = $themesDir . DIRECTORY_SEPARATOR . $rootFolder;
            if (!is_file($themePath . DIRECTORY_SEPARATOR . 'theme.json')) {
                // Delete invalid extracted folder
                $this->deleteDirectory($themePath);
                $this->session->flash('error', 'File theme.json tidak ditemukan di dalam paket.');
                return Response::redirect('/admin/appearance');
            }

            $this->session->flash('status', 'Tema berhasil diinstal.');
        } else {
            $this->session->flash('error', 'Gagal membuka file zip.');
        }

        return Response::redirect('/admin/appearance');
    }

    public function themeScreenshot(Request $request, array $vars): Response
    {
        $slug = trim($vars['slug'] ?? '');
        if ($slug === '') {
            return new Response('', 404);
        }

        $basePath = realpath(__DIR__ . '/../../../../..') ?: __DIR__ . '/../../../../..';
        $themePath = $basePath . DIRECTORY_SEPARATOR . 'qz_content/qz_themes' . DIRECTORY_SEPARATOR . $slug;

        $pngPath = $themePath . DIRECTORY_SEPARATOR . 'screenshot.png';
        $jpgPath = $themePath . DIRECTORY_SEPARATOR . 'screenshot.jpg';

        $filePath = is_file($pngPath) ? $pngPath : (is_file($jpgPath) ? $jpgPath : null);

        if ($filePath === null) {
            // Return empty 1x1 transparent gif or 404
            return new Response('', 404);
        }

        $mime = mime_content_type($filePath);
        $content = file_get_contents($filePath);

        $response = new Response($content, 200, ['Content-Type' => $mime ?: 'image/png']);
        return $response;
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
