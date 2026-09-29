<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** One rectangular shaped-grid recipe and its symbol-to-ingredient mapping. */
final readonly class ShapedRecipe implements RecipeDefinition
{
    /**
     * @param list<string> $shape
     * @param array<string, RecipeIngredient> $ingredientsBySymbol
     * @param list<RecipeOutput> $outputs
     * @internal Constructed by RecipeRegistry from validated admitted data.
     */
    public function __construct(
        private int $sourceIndex,
        private RecipeStation $station,
        private string $identifier,
        private array $shape,
        private array $ingredientsBySymbol,
        private array $outputs,
        private int $priority,
        private bool $assumeSymmetry,
        private ?RecipeUnlockContext $unlockContext,
    ) {}

    public function sourceIndex(): int { return $this->sourceIndex; }
    public function type(): RecipeType { return RecipeType::SHAPED; }
    public function station(): RecipeStation { return $this->station; }
    public function identifier(): string { return $this->identifier; }
    public function uuid(): ?string { return null; }
    public function ingredients(): array { return array_values($this->ingredientsBySymbol); }
    public function outputs(): array { return $this->outputs; }
    public function priority(): int { return $this->priority; }
    public function unlockContext(): ?RecipeUnlockContext { return $this->unlockContext; }
    public function deprecated(): bool { return $this->station === RecipeStation::DEPRECATED; }

    /** @return list<string> */
    public function shape(): array { return $this->shape; }

    /** @return array<string, RecipeIngredient> */
    public function ingredientsBySymbol(): array { return $this->ingredientsBySymbol; }

    public function width(): int { return strlen($this->shape[0]); }
    public function height(): int { return count($this->shape); }
    public function assumeSymmetry(): bool { return $this->assumeSymmetry; }
}
