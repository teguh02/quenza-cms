<?php
declare(strict_types=1);

namespace Quenza\Core\Translation;

use Quenza\Core\Support\Arr;
use RuntimeException;

final class Translator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $catalogues = [];

    public function __construct(
        private readonly string $languagePath,
        private string $locale,
        private readonly string $fallbackLocale,
    ) {
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function translate(string $key, array $replacements = [], ?string $locale = null): string
    {
        $resolvedLocale = $locale ?? $this->locale;

        $line = $this->resolveLine($resolvedLocale, $key)
            ?? $this->resolveLine($this->fallbackLocale, $key)
            ?? $key;

        return $this->replacePlaceholders($line, $replacements);
    }

    private function resolveLine(string $locale, string $key): ?string
    {
        $catalogue = $this->loadCatalogue($locale);
        $value = Arr::get($catalogue, $key);

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCatalogue(string $locale): array
    {
        if (array_key_exists($locale, $this->catalogues)) {
            return $this->catalogues[$locale];
        }

        $filePath = $this->languagePath . DIRECTORY_SEPARATOR . $locale . '.json';

        if (!is_file($filePath)) {
            return $this->catalogues[$locale] = [];
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException(sprintf('Gagal membaca file bahasa: %s', $filePath));
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Format file bahasa tidak valid: %s', $filePath));
        }

        return $this->catalogues[$locale] = $decoded;
    }

    private function replacePlaceholders(string $line, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $replacement = (string) $value;

            $line = str_replace(
                [':' . $key, '{{ ' . $key . ' }}', '{{' . $key . '}}'],
                $replacement,
                $line,
            );
        }

        return $line;
    }
}
