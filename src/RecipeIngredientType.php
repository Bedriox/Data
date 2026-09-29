<?php

declare(strict_types=1);

namespace Bedriox\Data;

enum RecipeIngredientType: string
{
    case ITEM = 'default';
    case TAG = 'item_tag';
}
