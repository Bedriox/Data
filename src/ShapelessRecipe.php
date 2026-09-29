<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** An ordered source record whose ingredients match independently of grid position. */
final readonly class ShapelessRecipe implements RecipeDefinition
{
    /**
     * @param list<RecipeIngredient> $ingredients
     * @param list<RecipeOutput> $outputs
     * @internal Constructed by RecipeRegistry from validated admitted data.
     */
    public function __construct(
        private int $sourceIndex,
        private RecipeType $type,
        private RecipeStation $station,
        private string $identifier,
        private array $ingredients,
        private array $outputs,
        private int $priority,
        private ?RecipeUnlockContext $unlockContext,
    ) {}

    public function sourceIndex(): int { return $this->sourceIndex; }
    public function type(): RecipeType { return $this->type; }
    public function station(): RecipeStation { return $this->station; }
    public function identifier(): string { return $this->identifier; }
    public function uuid(): ?string { return null; }
    public function ingredients(): array { return $this->ingredients; }
    public function outputs(): array { return $this->outputs; }
    public function priority(): int { return $this->priority; }
    public function unlockContext(): ?RecipeUnlockContext { return $this->unlockContext; }
    public function deprecated(): bool { return $this->station === RecipeStation::DEPRECATED; }
    public function preservesUserData(): bool { return $this->type === RecipeType::SHAPELESS_USER_DATA; }
}
