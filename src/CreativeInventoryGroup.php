<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable creative inventory group including its wire-facing presentation metadata. */
final readonly class CreativeInventoryGroup
{
    /** @internal Constructed by CreativeInventoryRegistry. */
    public static function fromNormalized(
        int $id,
        CreativeInventoryCategory $category,
        string $name,
        CreativeInventoryItem $icon,
    ): self {
        return new self($id, $category, $name, $icon);
    }

    private function __construct(
        private int $id,
        private CreativeInventoryCategory $category,
        private string $name,
        private CreativeInventoryItem $icon,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function category(): CreativeInventoryCategory
    {
        return $this->category;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function icon(): CreativeInventoryItem
    {
        return $this->icon;
    }
}
