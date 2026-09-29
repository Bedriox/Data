<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Vanilla little-endian NBT scalar types admitted in block-state properties. */
enum PersistentBlockStatePropertyType: int
{
    case Byte = 1;
    case Int = 3;
    case String = 8;
}
