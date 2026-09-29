<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** One immutable brewing-container conversion admitted with the active dataset. */
final readonly class ContainerMix
{
    /** @internal Values are validated by RecipeRegistry before construction. */
    public function __construct(
        private string $inputItemIdentifier,
        private string $reagentItemIdentifier,
        private string $outputItemIdentifier,
    ) {}

    public function inputItemIdentifier(): string { return $this->inputItemIdentifier; }

    public function reagentItemIdentifier(): string { return $this->reagentItemIdentifier; }

    public function outputItemIdentifier(): string { return $this->outputItemIdentifier; }
}
