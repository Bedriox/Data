<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Single validated authority for active dataset identity and artifact integrity metadata. */
final readonly class AdmittedDataManifest
{
    public const int MAX_MANIFEST_BYTES = 262_144;
    public const int MAX_ARTIFACT_BYTES = 67_108_864;
    public const int MAX_ARTIFACTS = 64;

    /** @var array<string, AdmittedArtifact> */
    private array $artifacts;

    /** @param array<array-key, mixed> $document */
    private function __construct(private string $manifestPath, array $document)
    {
        $schema = $document['schema'] ?? null;
        $expectedTopLevel = $schema === 2
            ? ['approval', 'artifacts', 'edition', 'gameVersion', 'generator', 'protocolVersion', 'qualifiedClientVersion', 'schema', 'source']
            : ['artifacts', 'edition', 'gameVersion', 'generator', 'protocolVersion', 'qualifiedClientVersion', 'schema', 'source'];
        $keys = array_keys($document);
        sort($keys, SORT_STRING);
        if ($keys !== $expectedTopLevel
            || ($schema !== 1 && $schema !== 2)
            || ($document['edition'] ?? null) !== 'bedrock'
            || !is_string($document['gameVersion'] ?? null)
            || !is_string($document['qualifiedClientVersion'] ?? null)
            || !is_int($document['protocolVersion'] ?? null)
            || !is_array($document['source'] ?? null)
            || !is_array($document['generator'] ?? null)
            || !is_array($document['artifacts'] ?? null)
            || !array_is_list($document['artifacts'])
            || $document['artifacts'] === []
            || count($document['artifacts']) > self::MAX_ARTIFACTS) {
            throw new RuntimeException('Dataset manifest has an invalid top-level shape.');
        }
        self::validateVersion($document['gameVersion'], 'game');
        self::validateVersion($document['qualifiedClientVersion'], 'qualified client');
        if ($document['protocolVersion'] < 1 || $document['protocolVersion'] > 100_000) {
            throw new RuntimeException('Dataset manifest protocol version is outside the supported range.');
        }
        self::validateSource($document['source'], $schema === 2);
        if ($schema === 1) {
            self::validateGenerator($document['generator']);
        } else {
            self::validateSchemaTwoGenerator($document['generator']);
            if (!is_array($document['approval'] ?? null)) {
                throw new RuntimeException('Dataset manifest approval metadata is invalid.');
            }
            self::validateSchemaTwoApproval($document['approval']);
        }

        $artifacts = [];
        $paths = [];
        foreach ($document['artifacts'] as $value) {
            if (!is_array($value) || !self::hasExactKeys($value, ['name', 'path', 'sha256', 'size'])
                || !is_string($value['name']) || !is_string($value['path'])
                || !is_int($value['size']) || !is_string($value['sha256'])) {
                throw new RuntimeException('Dataset manifest contains an invalid artifact record.');
            }
            try {
                $artifact = new AdmittedArtifact($value['name'], $value['path'], $value['size'], $value['sha256']);
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Dataset manifest contains an invalid artifact record.', previous: $error);
            }
            if (isset($artifacts[$artifact->name()]) || isset($paths[$artifact->path()])) {
                throw new RuntimeException('Dataset manifest contains a duplicate artifact name or path.');
            }
            $artifacts[$artifact->name()] = $artifact;
            $paths[$artifact->path()] = true;
        }
        $this->artifacts = $artifacts;
    }

    public static function bundled(): self
    {
        return self::fromFile(dirname(__DIR__) . '/' . BedrockVersion::ADMISSION_MANIFEST);
    }

    public static function fromFile(string $path): self
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Manifest path must be non-empty and contain no NUL bytes.');
        }
        $resolved = realpath($path);
        if (is_link($path) || !is_string($resolved) || !is_file($resolved)
            || self::normalizedAbsolutePath($resolved) !== self::normalizedAbsolutePath($path)) {
            throw new RuntimeException('Dataset manifest must be a direct, non-symlink file.');
        }
        $size = @filesize($resolved);
        if (!is_int($size) || $size < 1 || $size > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException('Dataset manifest size is outside the admitted range.');
        }
        $contents = @file_get_contents($resolved, false, null, 0, $size + 1);
        if (!is_string($contents) || strlen($contents) !== $size || preg_match('//u', $contents) !== 1) {
            throw new RuntimeException('Dataset manifest could not be read as bounded UTF-8.');
        }
        try {
            $document = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Dataset manifest is not valid bounded JSON.', previous: $error);
        }
        if (!is_array($document) || array_is_list($document)) {
            throw new RuntimeException('Dataset manifest root must be an object.');
        }
        return new self($resolved, $document);
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    /** @return array<string, AdmittedArtifact> */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function artifact(string $name): AdmittedArtifact
    {
        return $this->artifacts[$name]
            ?? throw new InvalidArgumentException('Unknown admitted registry artifact name.');
    }

    /** @param array<array-key, mixed> $source */
    private static function validateSource(array $source, bool $requiresUtcTimestamp): void
    {
        if (!self::hasExactKeys($source, ['commit', 'license', 'retrievedAt', 'rightsHolder', 'uri'])
            || !is_string($source['uri']) || filter_var($source['uri'], FILTER_VALIDATE_URL) === false
            || !is_string($source['commit']) || preg_match('/^[a-f0-9]{40}$/D', $source['commit']) !== 1
            || !is_string($source['retrievedAt'])
            || preg_match($requiresUtcTimestamp ? '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D' : '/^\d{4}-\d{2}-\d{2}$/D', $source['retrievedAt']) !== 1
            || !is_string($source['rightsHolder']) || trim($source['rightsHolder']) === '' || strlen($source['rightsHolder']) > 512
            || !is_string($source['license']) || trim($source['license']) === '' || strlen($source['license']) > 128) {
            throw new RuntimeException('Dataset manifest source metadata is invalid.');
        }
    }

    /** @param array<array-key, mixed> $generator */
    private static function validateGenerator(array $generator): void
    {
        if (!self::hasExactKeys($generator, ['command', 'path', 'redistributionApproved', 'reviewedBy', 'runtime'])
            || !is_string($generator['path']) || !AdmittedArtifact::isSafeRelativePath($generator['path'])
            || !is_string($generator['command']) || trim($generator['command']) === '' || strlen($generator['command']) > 2_048
            || !is_string($generator['runtime']) || trim($generator['runtime']) === '' || strlen($generator['runtime']) > 256
            || !is_string($generator['reviewedBy']) || trim($generator['reviewedBy']) === '' || strlen($generator['reviewedBy']) > 256
            || ($generator['redistributionApproved'] ?? null) !== true) {
            throw new RuntimeException('Dataset manifest generator metadata is invalid or redistribution is not approved.');
        }
    }

    /** @param array<array-key, mixed> $generator */
    private static function validateSchemaTwoGenerator(array $generator): void
    {
        if (!self::hasExactKeys($generator, ['name', 'revision', 'version'])
            || !is_string($generator['name']) || trim($generator['name']) === '' || strlen($generator['name']) > 128
            || !is_string($generator['version']) || preg_match('/^\d+\.\d+\.\d+$/D', $generator['version']) !== 1
            || !is_string($generator['revision']) || preg_match('/^[a-f0-9]{40}$/D', $generator['revision']) !== 1) {
            throw new RuntimeException('Dataset manifest generator identity is invalid.');
        }
    }

    /** @param array<array-key, mixed> $approval */
    private static function validateSchemaTwoApproval(array $approval): void
    {
        if (!self::hasExactKeys($approval, ['approvedAt', 'contentSha256', 'redistributionApproved', 'reviewedBy'])
            || !is_string($approval['reviewedBy']) || trim($approval['reviewedBy']) === '' || strlen($approval['reviewedBy']) > 256
            || !is_string($approval['approvedAt']) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $approval['approvedAt']) !== 1
            || ($approval['redistributionApproved'] ?? null) !== true
            || !is_string($approval['contentSha256']) || preg_match('/^[a-f0-9]{64}$/D', $approval['contentSha256']) !== 1) {
            throw new RuntimeException('Dataset manifest approval metadata is invalid.');
        }
    }

    private static function validateVersion(string $version, string $label): void
    {
        if (preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1 || strlen($version) > 32) {
            throw new RuntimeException("Dataset manifest {$label} version is invalid.");
        }
    }

    private static function normalizedAbsolutePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        if (count($data) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        return true;
    }
}
