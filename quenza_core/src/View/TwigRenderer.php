<?php
declare(strict_types=1);

namespace Quenza\Core\View;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Security\Security;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\TwigFunction;

final class TwigRenderer
{
    private Environment $twig;

    public function __construct(
        private readonly Application $app,
        private readonly Security $security,
        private readonly SessionManager $session,
        private readonly AuthManager $auth,
        private readonly Translator $translator,
    ) {
        $loader = new FilesystemLoader($this->app->basePath('quenza_core/resources/views'));

        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => (bool) $this->app->config('app.debug', false),
            'autoescape' => 'html',
        ]);

        $this->registerFunctions();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, [
            'app_name' => (string) $this->app->config('app.name', 'Quenza CMS'),
            'app_url' => (string) $this->app->config('app.url', 'http://localhost'),
            'config_locale' => $this->translator->locale(),
            'status_message' => $this->session->get('status'),
            'errors' => $this->session->errors(),
            ...$context,
        ]);
    }

    private function registerFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('trans', static fn (string $key, array $replacements = []): string => trans($key, $replacements)));
        $this->twig->addFunction(new TwigFunction('csrf_field', fn (): Markup => new Markup($this->security->csrfField(), 'UTF-8'), ['is_safe' => ['html']]));
        $this->twig->addFunction(new TwigFunction('old', fn (string $key, mixed $default = ''): mixed => $this->session->oldInput($key, $default)));
        $this->twig->addFunction(new TwigFunction('auth_user', fn (): ?array => $this->auth->user()));
        $this->twig->addFunction(new TwigFunction('is_authenticated', fn (): bool => $this->auth->check()));
        $this->twig->addFunction(new TwigFunction('has_role', fn (array|string $roles): bool => $this->auth->hasRole($roles)));
    }
}
