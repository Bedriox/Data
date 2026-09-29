<?php

declare(strict_types=1);

namespace Bedriox\Data;

enum CreativeInventoryCategory: string
{
    case Construction = 'construction';
    case Nature = 'nature';
    case Equipment = 'equipment';
    case Items = 'items';
}
