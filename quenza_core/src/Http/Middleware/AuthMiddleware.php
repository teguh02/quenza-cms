<?php
declare(strict_types=1);

namespace Quenza\Core\Http\Middleware;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly SessionManager $session,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->guest()) {
            $this->session->flash('status', trans('auth.login_required'));

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
