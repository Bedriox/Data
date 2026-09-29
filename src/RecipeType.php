<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Recipe record kinds admitted by the active Bedrock dataset. */
enum RecipeType: int
{
    case SHAPELESS = 0;
    case SHAPED = 1;
    case COMPLEX = 4;
    case SHAPELESS_USER_DATA = 5;
    case SMITHING_TRANSFORM = 8;
    case SMITHING_TRIM = 9;
}
