<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable creative entry; identity is independent from its possibly repeated item identifier. */
final readonly class CreativeInventoryEntry
{
    /** @internal Constructed by CreativeInventoryRegistry. */
    public static function fromNormalized(
        int $creativeNetworkId,
        CreativeInventoryCategory $category,
        ?int $groupId,
        CreativeInventoryItem $item,
    ): self {
        return new self($creativeNetworkId, $category, $groupId, $item);
    }

    private function __construct(
        private int $creativeNetworkId,
        private CreativeInventoryCategory $category,
        private ?int $groupId,
        private CreativeInventoryItem $item,
    ) {}

    public function creativeNetworkId(): int
    {
        return $this->creativeNetworkId;
    }

    public function category(): CreativeInventoryCategory
    {
        return $this->category;
    }

    public function groupId(): ?int
    {
        return $this->groupId;
    }

    public function item(): CreativeInventoryItem
    {
        return $this->item;
    }
}
