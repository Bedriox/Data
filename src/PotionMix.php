<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** One immutable metadata-aware brewing conversion admitted with the active dataset. */
final readonly class PotionMix
{
    /** @internal Values are validated by RecipeRegistry before construction. */
    public function __construct(
        private string $inputItemIdentifier,
        private int $inputMetadata,
        private string $reagentItemIdentifier,
        private int $reagentMetadata,
        private string $outputItemIdentifier,
        private int $outputMetadata,
    ) {}

    public function inputItemIdentifier(): string { return $this->inputItemIdentifier; }

    public function inputMetadata(): int { return $this->inputMetadata; }

    public function reagentItemIdentifier(): string { return $this->reagentItemIdentifier; }

    public function reagentMetadata(): int { return $this->reagentMetadata; }

    public function outputItemIdentifier(): string { return $this->outputItemIdentifier; }

    public function outputMetadata(): int { return $this->outputMetadata; }
}
