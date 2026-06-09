<?php
declare(strict_types=1);

namespace Quenza\Core\Packages;

use InvalidArgumentException;

final class ManifestValidator
{
    public function validateManifestFile(string $manifestPath, PackageScope $scope): PackageDefinition
    {
        $realManifestPath = realpath($manifestPath);

        if ($realManifestPath === false || !is_file($realManifestPath)) {
            throw new InvalidArgumentException(sprintf('Manifest tidak ditemukan: %s', $manifestPath));
        }

        $basePath = dirname($realManifestPath);
        $content = file_get_contents($realManifestPath);

        if ($content === false) {
            throw new InvalidArgumentException(sprintf('Manifest tidak dapat dibaca: %s', $realManifestPath));
        }

        $manifest = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($manifest)) {
            throw new InvalidArgumentException(sprintf('Format manifest tidak valid: %s', $realManifestPath));
        }

        $slug = $this->validateSlug((string) ($manifest['slug'] ?? ''));
        $name = trim((string) ($manifest['name'] ?? ''));
        $version = trim((string) ($manifest['version'] ?? '0.0.0'));
        $description = isset($manifest['description']) ? trim((string) $manifest['description']) : null;
        $author = isset($manifest['author']) ? trim((string) $manifest['author']) : null;
        $namespace = $this->validateNamespace((string) ($manifest['namespace'] ?? ''));
        $paths = is_array($manifest['paths'] ?? null) ? $manifest['paths'] : [];

        if ($name === '') {
            throw new InvalidArgumentException(sprintf('Manifest %s wajib memiliki field name.', $realManifestPath));
        }

        $sourcePath = $this->resolveSafeDirectory($basePath, (string) ($paths['src'] ?? 'src'), true);
        $migrationPath = $this->resolveSafeDirectory($basePath, (string) ($paths['migrations'] ?? 'database/migrations'), false);
        $seederPath = $this->resolveSafeDirectory($basePath, (string) ($paths['seeders'] ?? 'database/seeders'), false);

        return new PackageDefinition(
            $scope,
            $slug,
            $name,
            $version,
            $description,
            $author,
            $namespace,
            $basePath,
            $sourcePath,
            $migrationPath,
            $seederPath,
            $realManifestPath,
        );
    }

    private function validateSlug(string $slug): string
    {
        $normalizedSlug = trim($slug);

        if ($normalizedSlug === '' || !preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $normalizedSlug)) {
            throw new InvalidArgumentException(sprintf('Slug package tidak valid: %s', $slug));
        }

        return $normalizedSlug;
    }

    private function validateNamespace(string $namespace): string
    {
        $normalizedNamespace = trim($namespace);

        if ($normalizedNamespace === '' || !preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*\\\\$/', $normalizedNamespace)) {
            throw new InvalidArgumentException(sprintf('Namespace package tidak valid: %s', $namespace));
        }

        return $normalizedNamespace;
    }

    private function resolveSafeDirectory(string $packageBasePath, string $relativePath, bool $required): ?string
    {
        $trimmedPath = trim($relativePath);

        if ($trimmedPath === '') {
            if ($required) {
                throw new InvalidArgumentException(sprintf('Path wajib tidak boleh kosong di package: %s', $packageBasePath));
            }

            return null;
        }

        $candidatePath = $packageBasePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmedPath);

        if (!is_dir($candidatePath)) {
            if ($required) {
                throw new InvalidArgumentException(sprintf('Direktori wajib tidak ditemukan: %s', $candidatePath));
            }

            return null;
        }

        $resolvedPath = realpath($candidatePath);
        $resolvedBasePath = realpath($packageBasePath);

        if ($resolvedPath === false || $resolvedBasePath === false) {
            throw new InvalidArgumentException(sprintf('Gagal memvalidasi path package: %s', $candidatePath));
        }

        $basePrefix = rtrim($resolvedBasePath, '\\/') . DIRECTORY_SEPARATOR;
        $pathPrefix = rtrim($resolvedPath, '\\/') . DIRECTORY_SEPARATOR;

        if (!str_starts_with($pathPrefix, $basePrefix)) {
            throw new InvalidArgumentException(sprintf('Path package keluar dari direktori yang diizinkan: %s', $candidatePath));
        }

        return $resolvedPath;
    }
}
