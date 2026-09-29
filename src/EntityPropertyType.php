<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Client-visible data-driven property value kind admitted for an entity type. */
enum EntityPropertyType: int
{
    case INTEGER = 0;
    case FLOAT = 1;
    case BOOLEAN = 2;
    case ENUM = 3;
}
