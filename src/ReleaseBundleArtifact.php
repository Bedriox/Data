<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable record for one verified artifact in an approved release bundle. */
final readonly class ReleaseBundleArtifact
{
    public function __construct(private AdmittedArtifact $record, private string $absolutePath) {}

    public function record(): AdmittedArtifact
    {
        return $this->record;
    }

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }
}
