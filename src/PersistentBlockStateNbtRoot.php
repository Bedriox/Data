<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** @internal Strictly decoded vanilla persistent block-state root. */
final readonly class PersistentBlockStateNbtRoot
{
    /** @param list<PersistentBlockStateProperty> $properties */
    public function __construct(
        private string $identifier,
        private int $storageVersion,
        private array $properties,
        private string $encodedRoot,
        private int $nextOffset,
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function storageVersion(): int
    {
        return $this->storageVersion;
    }

    /** @return list<PersistentBlockStateProperty> */
    public function properties(): array
    {
        return $this->properties;
    }

    public function encodedRoot(): string
    {
        return $this->encodedRoot;
    }

    public function nextOffset(): int
    {
        return $this->nextOffset;
    }
}
