<?php
declare(strict_types=1);

namespace Quenza\Core\Http\Middleware;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/admin');
        }

        return $next($request);
    }
}
