<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable physical and presentation properties for one canonical block state. */
final readonly class BlockStateProperties
{
    /**
     * @param list<VoxelBox> $collisionShape
     * @param array{float, float, float, float, float, float} $outlineShape
     * @param array{float, float, float, float, float, float} $visualShape
     * @param array{float, float, float, float, float, float} $uiShape
     * @param array{float, float, float, float, float, float} $liquidClipShape
     */
    public function __construct(
        private CanonicalBlockState $blockState,
        private string $translationKey,
        private bool $solid,
        private float $hardness,
        private float $explosionResistance,
        private float $friction,
        private float $thickness,
        private bool $requiresCorrectToolForDrops,
        private float $translucency,
        private string $mapColor,
        private string $tintMethod,
        private int $lightEmission,
        private int $lightDampening,
        private int $burnOdds,
        private int $flameOdds,
        private bool $canContainLiquidSource,
        private string $liquidReactionOnTouch,
        private array $collisionShape,
        private array $outlineShape,
        private array $visualShape,
        private array $uiShape,
        private array $liquidClipShape,
    ) {}

    public function blockState(): CanonicalBlockState { return $this->blockState; }
    public function translationKey(): string { return $this->translationKey; }
    public function solid(): bool { return $this->solid; }
    public function hardness(): float { return $this->hardness; }
    public function explosionResistance(): float { return $this->explosionResistance; }
    public function friction(): float { return $this->friction; }
    public function thickness(): float { return $this->thickness; }
    public function requiresCorrectToolForDrops(): bool { return $this->requiresCorrectToolForDrops; }
    public function translucency(): float { return $this->translucency; }
    public function mapColor(): string { return $this->mapColor; }
    public function tintMethod(): string { return $this->tintMethod; }
    public function lightEmission(): int { return $this->lightEmission; }
    public function lightDampening(): int { return $this->lightDampening; }
    public function burnOdds(): int { return $this->burnOdds; }
    public function flameOdds(): int { return $this->flameOdds; }
    public function canContainLiquidSource(): bool { return $this->canContainLiquidSource; }
    public function liquidReactionOnTouch(): string { return $this->liquidReactionOnTouch; }

    /** @return list<VoxelBox> */
    public function collisionShape(): array { return $this->collisionShape; }

    /** @return array{float, float, float, float, float, float} */
    public function outlineShape(): array { return $this->outlineShape; }

    /** @return array{float, float, float, float, float, float} */
    public function visualShape(): array { return $this->visualShape; }

    /** @return array{float, float, float, float, float, float} */
    public function uiShape(): array { return $this->uiShape; }

    /** @return array{float, float, float, float, float, float} */
    public function liquidClipShape(): array { return $this->liquidClipShape; }
}
