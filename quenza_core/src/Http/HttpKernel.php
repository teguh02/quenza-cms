<?php
declare(strict_types=1);

namespace Quenza\Core\Http;

use Quenza\Core\Cms\OptionService;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Install\InstallationState;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Translation\Translator;

final class HttpKernel
{
    private bool $routesRegistered = false;

    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly SessionManager $session,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->session->start();
        $this->synchronizeLocale();

        /** @var InstallationState $installation */
        $installation = $this->app->get(InstallationState::class);

        if ($installation->shouldRedirectToInstall($request->path())) {
            return Response::redirect('/install');
        }

        if ($installation->shouldRedirectFromInstall($request->path())) {
            return Response::redirect($installation->postInstallRedirect());
        }

        $this->registerRoutes();

        return $this->router->dispatch($request, $this->app);
    }

    public function handleGlobals(): Response
    {
        return $this->handle(Request::fromGlobals());
    }

    private function registerRoutes(): void
    {
        if ($this->routesRegistered) {
            return;
        }

        $registerRoutes = require $this->app->basePath('routes/web.php');
        $registerRoutes($this->router);

        $this->routesRegistered = true;
    }

    private function synchronizeLocale(): void
    {
        /** @var Translator $translator */
        $translator = $this->app->get(Translator::class);

        $installerState = $this->session->get('installer', []);

        if (is_array($installerState) && isset($installerState['locale'])) {
            $translator->setLocale((string) $installerState['locale']);

            return;
        }

        /** @var InstallationState $installation */
        $installation = $this->app->get(InstallationState::class);

        if ($installation->requiresInstallation()) {
            return;
        }

        /** @var OptionService $options */
        $options = $this->app->get(OptionService::class);
        $locale = (string) $options->get('active_locale', $this->app->config('app.locale', 'id'));

        if ($locale !== '') {
            $translator->setLocale($locale);
        }
    }
}
