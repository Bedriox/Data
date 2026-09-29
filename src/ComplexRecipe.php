<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** UUID-addressed recipe whose input/output transformation is implemented by gameplay. */
final readonly class ComplexRecipe implements RecipeDefinition
{
    /** @internal Constructed by RecipeRegistry from validated admitted data. */
    public function __construct(private int $sourceIndex, private string $uuid) {}

    public function sourceIndex(): int { return $this->sourceIndex; }
    public function type(): RecipeType { return RecipeType::COMPLEX; }
    public function station(): ?RecipeStation { return null; }
    public function identifier(): ?string { return null; }
    public function uuid(): string { return $this->uuid; }
    public function ingredients(): array { return []; }
    public function outputs(): array { return []; }
    public function priority(): int { return 0; }
    public function unlockContext(): ?RecipeUnlockContext { return null; }
    public function deprecated(): bool { return false; }
}
