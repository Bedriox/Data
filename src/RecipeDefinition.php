<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Common immutable view over every classified recipe source record. */
interface RecipeDefinition
{
    public function sourceIndex(): int;

    public function type(): RecipeType;

    public function station(): ?RecipeStation;

    public function identifier(): ?string;

    public function uuid(): ?string;

    /** @return list<RecipeIngredient> */
    public function ingredients(): array;

    /** @return list<RecipeOutput> */
    public function outputs(): array;

    public function priority(): int;

    public function unlockContext(): ?RecipeUnlockContext;

    public function deprecated(): bool;
}
