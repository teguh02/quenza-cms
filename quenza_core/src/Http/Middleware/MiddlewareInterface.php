<?php
declare(strict_types=1);

namespace Quenza\Core\Http\Middleware;

use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
