<?php
declare(strict_types=1);

namespace Quenza\Core\Http\Middleware;

use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Security\Security;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = (string) $request->input('_token', '');

        if (!$this->security->validateCsrfToken($token)) {
            return Response::html('<h1>419</h1><p>CSRF token tidak valid.</p>', 419);
        }

        return $next($request);
    }
}
