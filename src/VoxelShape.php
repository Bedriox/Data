<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Immutable collection of bounded block-local boxes; an empty collection represents no collision. */
final readonly class VoxelShape
{
    /** @param list<VoxelBox> $boxes */
    public function __construct(private int $id, private array $boxes) {}

    public function id(): int { return $this->id; }

    /** @return list<VoxelBox> */
    public function boxes(): array { return $this->boxes; }
}
