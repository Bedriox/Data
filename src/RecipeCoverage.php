<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Complete mutually exclusive classification counts for one recipe registry. */
final readonly class RecipeCoverage
{
    /**
     * @param array<int, int> $byType Raw RecipeType value => count
     * @param array<string, int> $byStation Raw RecipeStation value => count
     * @internal Constructed by RecipeRegistry.
     */
    public function __construct(
        private int $total,
        private int $craftingTable,
        private int $workstations,
        private int $complex,
        private int $deprecated,
        private array $byType,
        private array $byStation,
    ) {}

    public function total(): int { return $this->total; }
    public function craftingTable(): int { return $this->craftingTable; }
    public function workstations(): int { return $this->workstations; }
    public function complex(): int { return $this->complex; }
    public function deprecated(): int { return $this->deprecated; }
    public function countForType(RecipeType $type): int { return $this->byType[$type->value] ?? 0; }
    public function countForStation(RecipeStation $station): int { return $this->byStation[$station->value] ?? 0; }

    /** @return array<int, int> */
    public function byType(): array { return $this->byType; }

    /** @return array<string, int> */
    public function byStation(): array { return $this->byStation; }
}
