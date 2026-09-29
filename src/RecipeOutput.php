<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** One immutable recipe result with optional bounded little-endian NBT. */
final readonly class RecipeOutput
{
    /** @internal Values are validated by RecipeRegistry before construction. */
    public function __construct(
        private string $itemIdentifier,
        private int $legacyItemId,
        private int $count,
        private int $damage,
        private ?string $nbt,
    ) {}

    public function itemIdentifier(): string
    {
        return $this->itemIdentifier;
    }

    public function legacyItemId(): int
    {
        return $this->legacyItemId;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function damage(): int
    {
        return $this->damage;
    }

    /** Bounded little-endian named root compound, or null. */
    public function nbt(): ?string
    {
        return $this->nbt;
    }
}
