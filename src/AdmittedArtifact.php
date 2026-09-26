<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

/** Immutable integrity record for one artifact in an admitted dataset manifest. */
final readonly class AdmittedArtifact
{
    public function __construct(
        private string $name,
        private string $path,
        private int $size,
        private string $sha256,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Artifact name is invalid.');
        }
        if (!self::isSafeRelativePath($path)) {
            throw new InvalidArgumentException('Artifact path must be a normalized forward-slash relative path.');
        }
        if ($size < 1 || $size > AdmittedDataManifest::MAX_ARTIFACT_BYTES) {
            throw new InvalidArgumentException('Artifact size is outside the admitted range.');
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException('Artifact SHA-256 must be lowercase hexadecimal.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 1_024 || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,255}$/D', $segment) !== 1) {
                return false;
            }
        }
        return implode('/', $segments) === $path;
    }
}
