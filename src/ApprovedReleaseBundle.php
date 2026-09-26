<?php

declare(strict_types=1);

namespace Bedriox\Data;

use RuntimeException;

/** A completely hash-verified, approved release bundle ready for a controlled public admission. */
final readonly class ApprovedReleaseBundle
{
    /** @param array<string, ReleaseBundleArtifact> $artifacts */
    public function __construct(
        private string $manifestContents,
        private string $gameVersion,
        private int $protocolVersion,
        private array $artifacts,
    ) {}

    public function gameVersion(): string
    {
        return $this->gameVersion;
    }

    public function protocolVersion(): int
    {
        return $this->protocolVersion;
    }

    /** @return array<string, ReleaseBundleArtifact> */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function publicManifestPath(): string
    {
        return sprintf('manifests/bedrock-%s-protocol-%d-schema-2.json', $this->gameVersion, $this->protocolVersion);
    }

    /**
     * Copies only verified bundle members into a clean destination in the Data repository.
     * Existing paths are immutable and are never replaced.
     */
    public function applyTo(string $repositoryRoot): void
    {
        $root = realpath($repositoryRoot);
        if (!is_string($root) || !is_dir($root) || !is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new RuntimeException('Admission target must resolve to a Bedriox Data repository root.');
        }
        $composer = json_decode((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'composer.json'), true);
        if (!is_array($composer) || ($composer['name'] ?? null) !== 'bedriox/data') {
            throw new RuntimeException('Admission target Composer package is not bedriox/data.');
        }
        $manifestTarget = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->publicManifestPath());
        $targets = [$manifestTarget];
        foreach ($this->artifacts as $artifact) {
            $targets[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact->record()->path());
        }
        foreach ($targets as $target) {
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException('Admission cannot replace an existing immutable path.');
            }
        }

        $createdFiles = [];
        $createdDirectories = [];
        try {
            foreach ($this->artifacts as $artifact) {
                $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact->record()->path());
                self::ensureDirectory(dirname($target), $root, $createdDirectories);
                self::copyVerified($artifact, $target);
                $createdFiles[] = $target;
            }
            self::ensureDirectory(dirname($manifestTarget), $root, $createdDirectories);
            self::writeNewFile($manifestTarget, $this->manifestContents);
            $createdFiles[] = $manifestTarget;
        } catch (\Throwable $error) {
            foreach (array_reverse($createdFiles) as $file) {
                if (is_file($file) && !is_link($file)) {
                    @unlink($file);
                }
            }
            foreach (array_reverse($createdDirectories) as $directory) {
                @rmdir($directory);
            }
            throw $error;
        }
    }

    /** @param list<string> $createdDirectories */
    private static function ensureDirectory(string $directory, string $root, array &$createdDirectories): void
    {
        if (is_dir($directory) && !is_link($directory)) {
            return;
        }
        if (is_link($directory)) {
            throw new RuntimeException('Admission target directory cannot be a symbolic link.');
        }
        $parent = dirname($directory);
        if ($parent === $directory || !str_starts_with(self::normalize($directory), self::normalize($root) . '/')) {
            throw new RuntimeException('Admission target directory escapes the repository root.');
        }
        self::ensureDirectory($parent, $root, $createdDirectories);
        if (!mkdir($directory, 0777)) {
            throw new RuntimeException('Unable to create an admission target directory.');
        }
        $createdDirectories[] = $directory;
    }

    private static function copyVerified(ReleaseBundleArtifact $artifact, string $target): void
    {
        $record = $artifact->record();
        $size = $record->size();
        if ($size < 1 || $size > AdmittedDataManifest::MAX_ARTIFACT_BYTES) {
            throw new RuntimeException('Release bundle artifact size changed outside the admitted range.');
        }
        $resolved = realpath($artifact->absolutePath());
        if (is_link($artifact->absolutePath()) || !is_string($resolved)
            || self::normalize($resolved) !== self::normalize($artifact->absolutePath())) {
            throw new RuntimeException('Release bundle artifact path changed after validation.');
        }
        $contents = @file_get_contents($resolved, false, null, 0, $size);
        if (!is_string($contents) || strlen($contents) !== $size || hash('sha256', $contents) !== $record->sha256()) {
            throw new RuntimeException('Release bundle artifact changed after validation.');
        }
        self::writeNewFile($target, $contents);
    }

    private static function writeNewFile(string $path, string $contents): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create an immutable admission output.');
        }
        $writeError = null;
        try {
            if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
                throw new RuntimeException('Unable to write an immutable admission output.');
            }
        } catch (\Throwable $error) {
            $writeError = $error;
        } finally {
            fclose($handle);
        }
        if ($writeError !== null) {
            @unlink($path);
            throw $writeError;
        }
    }

    private static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
