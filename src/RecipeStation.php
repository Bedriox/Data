<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Workstation classification carried by a recipe source record. */
enum RecipeStation: string
{
    case CRAFTING_TABLE = 'crafting_table';
    case FURNACE = 'furnace';
    case BLAST_FURNACE = 'blast_furnace';
    case SMOKER = 'smoker';
    case CAMPFIRE = 'campfire';
    case SOUL_CAMPFIRE = 'soul_campfire';
    case STONECUTTER = 'stonecutter';
    case CARTOGRAPHY_TABLE = 'cartography_table';
    case SMITHING_TABLE = 'smithing_table';
    case DEPRECATED = 'deprecated';
}
