<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable canonical association between a placeable item and its default block state. */
final readonly class BlockItemMapping
{
    /** @internal Constructed by BlockItemMappingRegistry. */
    public function __construct(
        private string $itemIdentifier,
        private CanonicalBlockState $blockState,
    ) {}

    public function itemIdentifier(): string
    {
        return $this->itemIdentifier;
    }

    public function blockState(): CanonicalBlockState
    {
        return $this->blockState;
    }
}
