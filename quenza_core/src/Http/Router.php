<?php
declare(strict_types=1);

namespace Quenza\Core\Http;

use InvalidArgumentException;
use Quenza\Core\Foundation\Application;
use Quenza\Core\Http\Middleware\MiddlewareInterface;

final class Router
{
    /**
     * @var list<array{method: string, pattern: string, handler: mixed, middleware: list<string|callable>}>
     */
    private array $routes = [];

    /**
     * @param list<string|callable> $middleware
     */
    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param list<string|callable> $middleware
     */
    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param list<string|callable> $middleware
     */
    public function add(string $method, string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request, Application $app): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameters = $this->matchPattern($route['pattern'], $request->path());

            if ($parameters === null) {
                continue;
            }

            $requestWithParameters = $request->withRouteParameters($parameters);
            $handler = $this->resolveHandler($route['handler'], $app);

            $core = function (Request $incomingRequest) use ($handler): Response {
                return $this->normalizeResponse($handler($incomingRequest));
            };

            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                function (callable $next, mixed $middleware) use ($app): callable {
                    return function (Request $incomingRequest) use ($middleware, $next, $app): Response {
                        $resolvedMiddleware = is_string($middleware) ? $app->make($middleware) : $middleware;

                        if ($resolvedMiddleware instanceof MiddlewareInterface) {
                            return $resolvedMiddleware->handle($incomingRequest, $next);
                        }

                        if (is_callable($resolvedMiddleware)) {
                            return $this->normalizeResponse($resolvedMiddleware($incomingRequest, $next));
                        }

                        throw new InvalidArgumentException('Middleware route tidak valid.');
                    };
                },
                $core,
            );

            return $pipeline($requestWithParameters);
        }

        return Response::notFound('<h1>404</h1><p>Halaman tidak ditemukan.</p>');
    }

    /**
     * @return array<string, string>|null
     */
    private function matchPattern(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        $regex = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $pattern,
        );

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    private function resolveHandler(mixed $handler, Application $app): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            $controller = $app->make($handler[0]);

            return [$controller, $handler[1]];
        }

        throw new InvalidArgumentException('Handler route tidak valid.');
    }

    private function normalizeResponse(mixed $response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_string($response)) {
            return Response::html($response);
        }

        throw new InvalidArgumentException('Handler route harus mengembalikan Response atau string.');
    }
}
