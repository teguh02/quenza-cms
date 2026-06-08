<?php
declare(strict_types=1);

namespace Quenza\Core\Controller;

use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Install\InstallerService;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;
use Throwable;

final class InstallController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly SessionManager $session,
        private readonly InstallerService $installer,
    ) {
    }

    public function show(Request $request): Response
    {
        $state = $this->installerState();
        $stepInput = $request->input('step');
        $step = $stepInput === null
            ? 1
            : max(1, min(3, (int) $stepInput));
        $runtimeContext = (string) ($state['runtime_context'] ?? runtime()->context());
        $manualConfigurationDetected = (bool) ($state['manual_configuration_detected'] ?? $this->installer->manualConfigurationDetected());

        return match ($step) {
            1 => Response::html($this->view->render('install/language.twig', [
                'page_title' => trans('install.title'),
                'installer_step' => 1,
                'selected_locale' => $state['locale'] ?? 'id',
                'manual_configuration_detected' => $manualConfigurationDetected,
            ])),
            2 => Response::html($this->view->render('install/database.twig', [
                'page_title' => trans('install.database.title'),
                'installer_step' => 2,
                'selected_driver' => $state['database']['driver'] ?? 'sqlite',
                'database_config' => $state['database'] ?? [
                    'driver' => 'sqlite',
                    'host' => '127.0.0.1',
                    'port' => 3306,
                    'database' => 'quenza_cms',
                    'username' => 'root',
                    'password' => '',
                    'sqlite_path' => 'storage/database/quenza.db',
                ],
                'runtime_context' => $runtimeContext,
                'manual_configuration_detected' => $manualConfigurationDetected,
            ])),
            default => Response::html($this->view->render('install/site.twig', [
                'page_title' => trans('install.site.title'),
                'installer_step' => 3,
                'site_config' => $state['site'] ?? [],
                'runtime_context' => $runtimeContext,
                'manual_configuration_detected' => $manualConfigurationDetected,
            ])),
        };
    }

    public function setLanguage(Request $request): Response
    {
        $locale = (string) $request->input('locale', 'id');

        if (!in_array($locale, ['id', 'en'], true)) {
            $locale = 'id';
        }

        $state = $this->installerState();
        $state['locale'] = $locale;
        $this->session->put('installer', $state);

        return Response::redirect('/install?step=2');
    }

    public function setDatabase(Request $request): Response
    {
        $result = $this->installer->validateDatabaseConfiguration($request->allInput());

        if (!$result['valid']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flash('status', reset($result['errors']) ?: trans('install.database.failed'));

            return Response::redirect('/install?step=2');
        }

        $state = $this->installerState();
        $state['database'] = $result['data'];
        $this->session->put('installer', $state);
        $this->session->flash('status', trans('install.database.success'));

        return Response::redirect('/install?step=3');
    }

    public function install(Request $request): Response
    {
        $state = $this->installerState();

        if (!isset($state['database']) || !is_array($state['database'])) {
            $this->session->flash('status', trans('install.database.required'));

            return Response::redirect('/install?step=2');
        }

        $prefilledPassword = isset($state['site']['admin_password']) && is_string($state['site']['admin_password']) && $state['site']['admin_password'] !== ''
            ? (string) $state['site']['admin_password']
            : null;

        $siteValidation = $this->installer->validateSiteConfiguration($request->allInput(), $prefilledPassword);

        if (!$siteValidation['valid']) {
            $state['site'] = $siteValidation['data'];
            $this->session->put('installer', $state);
            $this->session->flashErrors($siteValidation['errors']);
            $this->session->flash('status', reset($siteValidation['errors']) ?: trans('install.site.failed'));

            return Response::redirect('/install?step=3');
        }

        $state['site'] = $siteValidation['data'];
        $this->session->put('installer', $state);

        try {
            $this->installer->install(
                (string) ($state['locale'] ?? 'id'),
                (array) $state['database'],
                (array) $state['site'],
                $request->baseUrl(),
            );
        } catch (Throwable $throwable) {
            $this->session->flash('status', trans('install.process.failed') . ' ' . $throwable->getMessage());

            return Response::redirect('/install?step=3');
        }

        $this->session->put('installer_success', [
            'site_title' => $state['site']['site_title'],
        ]);

        return Response::redirect('/install/success');
    }

    public function success(Request $request): Response
    {
        $success = $this->session->pull('installer_success', []);

        if (!is_array($success) || $success === []) {
            return Response::redirect('/login');
        }

        return Response::html($this->view->render('install/success.twig', [
            'page_title' => trans('install.success.title'),
            'site_title' => $success['site_title'] ?? config('app.name', 'Quenza CMS'),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function installerState(): array
    {
        $state = $this->session->get('installer', []);
        $prefill = $this->installer->prefill();

        if (!is_array($state) || $state === []) {
            $this->session->put('installer', $prefill);

            return $prefill;
        }

        return array_replace_recursive($prefill, $state);
    }

}
