<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use Bedriox\Data\BedrockDataSet;
use Bedriox\Data\BedrockVersion;
use Bedriox\Data\ComplexRecipe;
use Bedriox\Data\ContainerMix;
use Bedriox\Data\ItemNetworkRegistry;
use Bedriox\Data\RecipeIngredientType;
use Bedriox\Data\RecipeRegistry;
use Bedriox\Data\RecipeStation;
use Bedriox\Data\RecipeType;
use Bedriox\Data\PotionMix;
use Bedriox\Data\ShapedRecipe;
use Bedriox\Data\ShapelessRecipe;
use Bedriox\Data\SmithingTransformRecipe;
use Bedriox\Data\SmithingTrimRecipe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RecipeRegistryTest extends TestCase
{
    public function testBundledRegistryClassifiesEverySourceRecord(): void
    {
        $registry = BedrockDataSet::bundled()->recipeRegistry();
        $coverage = $registry->coverage();

        self::assertSame(4_768, $coverage->total());
        self::assertSame(4_128, $coverage->craftingTable());
        self::assertSame(592, $coverage->workstations());
        self::assertSame(14, $coverage->complex());
        self::assertSame(34, $coverage->deprecated());
        self::assertSame(4_768, $coverage->craftingTable() + $coverage->workstations() + $coverage->complex() + $coverage->deprecated());
        self::assertSame([
            0 => 2_068,
            1 => 1_217,
            4 => 14,
            5 => 1_456,
            8 => 12,
            9 => 1,
        ], $coverage->byType());
        self::assertSame([
            'blast_furnace' => 62,
            'campfire' => 9,
            'cartography_table' => 2,
            'crafting_table' => 4_128,
            'deprecated' => 34,
            'furnace' => 134,
            'smithing_table' => 13,
            'smoker' => 9,
            'soul_campfire' => 9,
            'stonecutter' => 354,
        ], $coverage->byStation());

        $sourceIndexes = array_map(static fn($recipe): int => $recipe->sourceIndex(), $registry->recipes());
        self::assertSame(range(0, 4_767), $sourceIndexes);
        self::assertCount(4_768, array_unique($sourceIndexes, SORT_NUMERIC));
        self::assertContainsOnlyInstancesOf(ShapedRecipe::class, array_filter(
            $registry->recipes(),
            static fn($recipe): bool => $recipe->type() === RecipeType::SHAPED,
        ));
        self::assertContainsOnlyInstancesOf(ComplexRecipe::class, $registry->complexRecipes());
    }

    public function testBundledRegistryExposesTypedCraftingAndDeferredRecipes(): void
    {
        $registry = BedrockDataSet::bundled()->recipeRegistry();
        $workbench = $registry->recipesForIdentifier('minecraft:WorkBench_recipeId');
        self::assertCount(1, $workbench);
        self::assertInstanceOf(ShapedRecipe::class, $workbench[0]);
        self::assertSame(['AA', 'AA'], $workbench[0]->shape());
        self::assertSame(2, $workbench[0]->width());
        self::assertSame(2, $workbench[0]->height());
        self::assertSame(RecipeIngredientType::TAG, $workbench[0]->ingredientsBySymbol()['A']->type());
        self::assertSame('minecraft:planks', $workbench[0]->ingredientsBySymbol()['A']->itemTag());
        self::assertSame('minecraft:crafting_table', $workbench[0]->outputs()[0]->itemIdentifier());

        $userDataRecipe = array_values(array_filter(
            $registry->craftingTableRecipes(),
            static fn($recipe): bool => $recipe->type() === RecipeType::SHAPELESS_USER_DATA,
        ))[0];
        self::assertInstanceOf(ShapelessRecipe::class, $userDataRecipe);
        self::assertTrue($userDataRecipe->preservesUserData());

        self::assertContainsOnlyInstancesOf(
            SmithingTransformRecipe::class,
            array_filter($registry->workstationRecipes(), static fn($recipe): bool => $recipe->type() === RecipeType::SMITHING_TRANSFORM),
        );
        self::assertContainsOnlyInstancesOf(
            SmithingTrimRecipe::class,
            array_filter($registry->workstationRecipes(), static fn($recipe): bool => $recipe->type() === RecipeType::SMITHING_TRIM),
        );
        self::assertSame(
            '442d85ed-8272-4543-a6f1-418f90ded05d',
            $registry->complexRecipe('442D85ED-8272-4543-A6F1-418F90DED05D')->uuid(),
        );
        self::assertSame(1_470, $registry->coverage()->countForType(RecipeType::SHAPELESS)
            - $registry->coverage()->countForStation(RecipeStation::FURNACE)
            - $registry->coverage()->countForStation(RecipeStation::BLAST_FURNACE)
            - $registry->coverage()->countForStation(RecipeStation::SMOKER)
            - $registry->coverage()->countForStation(RecipeStation::CAMPFIRE)
            - $registry->coverage()->countForStation(RecipeStation::SOUL_CAMPFIRE)
            - $registry->coverage()->countForStation(RecipeStation::STONECUTTER)
            - $registry->coverage()->countForStation(RecipeStation::CARTOGRAPHY_TABLE)
            - 19);
    }

    public function testBundledRegistryExposesTypedBrewingMixes(): void
    {
        $registry = BedrockDataSet::bundled()->recipeRegistry();

        self::assertCount(2, $registry->containerMixes());
        self::assertContainsOnlyInstancesOf(ContainerMix::class, $registry->containerMixes());
        self::assertSame('minecraft:potion', $registry->containerMixes()[0]->inputItemIdentifier());
        self::assertSame('minecraft:gunpowder', $registry->containerMixes()[0]->reagentItemIdentifier());
        self::assertSame('minecraft:splash_potion', $registry->containerMixes()[0]->outputItemIdentifier());

        self::assertCount(210, $registry->potionMixes());
        self::assertContainsOnlyInstancesOf(PotionMix::class, $registry->potionMixes());
        self::assertSame('minecraft:potion', $registry->potionMixes()[0]->inputItemIdentifier());
        self::assertSame(4, $registry->potionMixes()[0]->inputMetadata());
        self::assertSame('minecraft:breeze_rod', $registry->potionMixes()[0]->reagentItemIdentifier());
        self::assertSame(0, $registry->potionMixes()[0]->reagentMetadata());
        self::assertSame('minecraft:potion', $registry->potionMixes()[0]->outputItemIdentifier());
        self::assertSame(43, $registry->potionMixes()[0]->outputMetadata());
    }

    /** @param array<string, mixed> $document */
    #[DataProvider('invalidDocuments')]
    public function testMalformedOrUnclassifiableRecordsFailClosed(array $document, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        RecipeRegistry::fromJson((string) json_encode($document, JSON_THROW_ON_ERROR), self::items());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'unknown type' => [self::document([['type' => 99]]), 'unknown type'];
        yield 'unknown station' => [self::document([self::shapeless(['block' => 'unknown'])]), 'unknown station'];
        yield 'unknown item' => [self::document([self::shapeless([
            'input' => [['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:missing', 'itemId' => 2, 'type' => 'default']],
        ])]), 'unknown item'];
        yield 'oversized ingredient count' => [self::document([self::shapeless([
            'input' => array_fill(0, 10, ['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:stone', 'itemId' => 1, 'type' => 'default']),
        ])]), 'invalid shape'];
        yield 'undefined shaped symbol' => [self::document([self::shaped(['shape' => ['AB']])]), 'undefined symbol'];
        yield 'unused shaped symbol' => [self::document([self::shaped([
            'input' => [
                'A' => ['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:stone', 'itemId' => 1, 'type' => 'default'],
                'B' => ['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:dirt', 'itemId' => 2, 'type' => 'default'],
            ],
        ])]), 'unused or missing'];
        yield 'duplicate named identity' => [self::document([self::shapeless(), self::shapeless()]), 'duplicate typed identity'];
        yield 'duplicate complex UUID' => [self::document([
            ['type' => 4, 'uuid' => '00000000-0000-0000-0000-000000000001'],
            ['type' => 4, 'uuid' => '00000000-0000-0000-0000-000000000001'],
        ]), 'duplicate typed identity'];
        yield 'unknown field' => [self::document([self::shapeless(['unexpected' => true])]), 'invalid shape'];
        yield 'malformed mix' => [[
            'containerMixes' => [['inputId' => 'minecraft:stone']],
            'potionMixes' => [],
            'recipes' => [],
            'version' => BedrockVersion::PROTOCOL,
        ], 'invalid container mix'];
    }

    /** @param list<array<string, mixed>> $recipes
     *  @return array<string, mixed>
     */
    private static function document(array $recipes): array
    {
        return ['containerMixes' => [], 'potionMixes' => [], 'recipes' => $recipes, 'version' => BedrockVersion::PROTOCOL];
    }

    /** @param array<string, mixed> $replace
     *  @return array<string, mixed>
     */
    private static function shapeless(array $replace = []): array
    {
        return array_replace([
            'block' => 'crafting_table',
            'id' => 'minecraft:test_recipe',
            'input' => [['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:stone', 'itemId' => 1, 'type' => 'default']],
            'output' => [['id' => 'minecraft:dirt', 'legacyId' => 2]],
            'priority' => 0,
            'type' => 0,
        ], $replace);
    }

    /** @param array<string, mixed> $replace
     *  @return array<string, mixed>
     */
    private static function shaped(array $replace = []): array
    {
        return array_replace([
            'assumeSymetry' => true,
            'block' => 'crafting_table',
            'id' => 'minecraft:test_shaped',
            'input' => ['A' => ['auxValue' => 0, 'count' => 1, 'id' => 'minecraft:stone', 'itemId' => 1, 'type' => 'default']],
            'output' => [['id' => 'minecraft:dirt', 'legacyId' => 2]],
            'priority' => 0,
            'shape' => ['A'],
            'type' => 1,
        ], $replace);
    }

    private static function items(): ItemNetworkRegistry
    {
        return ItemNetworkRegistry::fromRequiredItems([
            'minecraft:stone' => ['runtime_id' => 1, 'component_based' => false, 'version' => 2],
            'minecraft:dirt' => ['runtime_id' => 2, 'component_based' => false, 'version' => 2],
        ]);
    }
}
