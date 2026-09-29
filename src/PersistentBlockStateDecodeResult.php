<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

final readonly class PersistentBlockStateDecodeResult
{
    public function __construct(private PersistentBlockState $state, private int $nextOffset)
    {
        if ($nextOffset < 0) {
            throw new InvalidArgumentException('Persistent block-state next offset cannot be negative.');
        }
    }

    public function state(): PersistentBlockState
    {
        return $this->state;
    }

    public function nextOffset(): int
    {
        return $this->nextOffset;
    }
}
