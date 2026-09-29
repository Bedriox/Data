<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Deferred dynamic smithing-trim record retained without inventing a static output. */
final readonly class SmithingTrimRecipe implements RecipeDefinition
{
    /** @internal Constructed by RecipeRegistry from validated admitted data. */
    public function __construct(
        private int $sourceIndex,
        private RecipeIngredient $template,
        private RecipeIngredient $base,
        private RecipeIngredient $addition,
    ) {}

    public function sourceIndex(): int { return $this->sourceIndex; }
    public function type(): RecipeType { return RecipeType::SMITHING_TRIM; }
    public function station(): RecipeStation { return RecipeStation::SMITHING_TABLE; }
    public function identifier(): ?string { return null; }
    public function uuid(): ?string { return null; }
    public function ingredients(): array { return [$this->template, $this->base, $this->addition]; }
    public function outputs(): array { return []; }
    public function priority(): int { return 0; }
    public function unlockContext(): ?RecipeUnlockContext { return null; }
    public function deprecated(): bool { return false; }
    public function template(): RecipeIngredient { return $this->template; }
    public function base(): RecipeIngredient { return $this->base; }
    public function addition(): RecipeIngredient { return $this->addition; }
}
