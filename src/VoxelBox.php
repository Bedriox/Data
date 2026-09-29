<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Axis-aligned unit-block box in normalized block-local coordinates. */
final readonly class VoxelBox
{
    public function __construct(
        private float $minX,
        private float $minY,
        private float $minZ,
        private float $maxX,
        private float $maxY,
        private float $maxZ,
    ) {}

    /** @return array{float, float, float} */
    public function minimum(): array
    {
        return [$this->minX, $this->minY, $this->minZ];
    }

    /** @return array{float, float, float} */
    public function maximum(): array
    {
        return [$this->maxX, $this->maxY, $this->maxZ];
    }
}
