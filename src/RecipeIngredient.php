<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** One immutable exact-item or item-tag recipe requirement. */
final readonly class RecipeIngredient
{
    private function __construct(
        private RecipeIngredientType $type,
        private int $count,
        private ?string $itemIdentifier,
        private ?string $itemTag,
        private ?int $auxValue,
        private ?int $legacyItemId,
    ) {}

    /** @internal Values are validated by RecipeRegistry before construction. */
    public static function item(string $identifier, int $count, int $auxValue, int $legacyItemId): self
    {
        return new self(RecipeIngredientType::ITEM, $count, $identifier, null, $auxValue, $legacyItemId);
    }

    /** @internal Values are validated by RecipeRegistry before construction. */
    public static function tag(string $tag, int $count): self
    {
        return new self(RecipeIngredientType::TAG, $count, null, $tag, null, null);
    }

    public function type(): RecipeIngredientType
    {
        return $this->type;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function itemIdentifier(): ?string
    {
        return $this->itemIdentifier;
    }

    public function itemTag(): ?string
    {
        return $this->itemTag;
    }

    public function auxValue(): ?int
    {
        return $this->auxValue;
    }

    public function legacyItemId(): ?int
    {
        return $this->legacyItemId;
    }
}
