<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

final readonly class ArtifactRecord
{
    public function __construct(
        public string $path,
        public string $sha256,
        public string $sourceUri,
        public string $retrievedAt,
        public string $rightsHolder,
        public string $licenseExpression,
        public string $generator,
        public string $reviewedBy,
        public bool $redistributionApproved,
    ) {
        if (!AdmittedArtifact::isSafeRelativePath($path)) {
            throw new InvalidArgumentException('Artifact path must be a safe, non-empty, forward-slash relative path.');
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException('Artifact SHA-256 must be 64 lowercase hexadecimal characters.');
        }
        if (filter_var($sourceUri, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Artifact source URI must be an absolute URL.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $retrievedAt) !== 1) {
            throw new InvalidArgumentException('Retrieval date must use YYYY-MM-DD.');
        }
        if (trim($rightsHolder) === '' || trim($licenseExpression) === '' || trim($generator) === '' || trim($reviewedBy) === '') {
            throw new InvalidArgumentException('Rights holder, license, generator, and reviewer identities are required.');
        }
    }

    public function matchesContents(string $contents): bool
    {
        return hash('sha256', $contents) === $this->sha256;
    }
}
