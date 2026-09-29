<?php

declare(strict_types=1);

namespace Bedriox\Data;

enum RecipeUnlockContext: string
{
    case ALWAYS_UNLOCKED = 'ALWAYS_UNLOCKED';
    case PLAYER_HAS_MANY_ITEMS = 'PLAYER_HAS_MANY_ITEMS';
    case PLAYER_IN_WATER = 'PLAYER_IN_WATER';
}
