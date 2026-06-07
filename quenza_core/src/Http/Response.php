<?php
declare(strict_types=1);

namespace Quenza\Core\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $content,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }

    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self('', $status, ['Location' => $location, ...$headers]);
    }

    public static function notFound(string $content = 'Halaman tidak ditemukan.'): self
    {
        return self::html($content, 404);
    }

    public function content(): string
    {
        return $this->content;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $default;
    }

    public function send(): void
    {
        if (PHP_SAPI !== 'cli') {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value), true);
            }
        }

        echo $this->content;
    }
}
