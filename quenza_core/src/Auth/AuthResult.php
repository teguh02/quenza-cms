<?php
declare(strict_types=1);

namespace Quenza\Core\Auth;

readonly class AuthResult
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        public bool $successful,
        public string $message,
        public array $errors = [],
    ) {
    }

    /**
     * @param array<string, string> $errors
     */
    public static function failure(string $message, array $errors = []): self
    {
        return new self(false, $message, $errors);
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }
}
