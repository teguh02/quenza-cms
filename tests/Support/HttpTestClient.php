<?php
declare(strict_types=1);

namespace Tests\Support;

use Quenza\Core\Http\HttpKernel;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;

final class HttpTestClient
{
    public function __construct(
        private readonly HttpKernel $kernel,
    ) {
    }

    public function get(string $path, string $ipAddress = '127.0.0.1'): Response
    {
        return $this->kernel->handle(Request::create('GET', $path, server: ['REMOTE_ADDR' => $ipAddress]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function post(string $path, array $payload, string $ipAddress = '127.0.0.1'): Response
    {
        return $this->kernel->handle(Request::create('POST', $path, request: $payload, server: ['REMOTE_ADDR' => $ipAddress]));
    }

    public function csrfToken(string $path): string
    {
        return $this->extractCsrfToken($this->get($path)->content());
    }

    public function extractCsrfToken(string $html): string
    {
        if (preg_match('/name="_token" value="([^"]+)"/', $html, $matches) !== 1) {
            throw new \RuntimeException('CSRF token tidak ditemukan pada response HTML test.');
        }

        return $matches[1];
    }
}
