<?php
declare(strict_types=1);

namespace Quenza\Core\Http;

use Quenza\Core\Foundation\Application;
use Quenza\Core\Session\SessionManager;

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
}
