<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use ValueError;

/** Immutable, fully classified recipe catalog from one admitted dataset. */
final readonly class RecipeRegistry
{
    public const int MAX_JSON_BYTES = 8_388_608;
    public const int MAX_RECIPES = 8_192;
    public const int MAX_INGREDIENTS = 9;
    public const int MAX_OUTPUTS = 8;
    public const int MAX_ITEM_NBT_BYTES = 65_536;

    /** @var list<RecipeDefinition> */
    private array $recipes;

    /** @var list<RecipeDefinition> */
    private array $craftingTableRecipes;

    /** @var list<RecipeDefinition> */
    private array $workstationRecipes;

    /** @var list<ComplexRecipe> */
    private array $complexRecipes;

    /** @var list<RecipeDefinition> */
    private array $deprecatedRecipes;

    /** @var array<string, list<RecipeDefinition>> */
    private array $byIdentifier;

    /** @var array<string, ComplexRecipe> */
    private array $byUuid;

    /** @var array<string, list<RecipeDefinition>> */
    private array $byStation;

    /** @var array<string, array<string, list<RecipeDefinition>>> */
    private array $byStationAndExactInput;

    /** @var array<string, array<string, list<RecipeDefinition>>> */
    private array $byStationAndInputTag;

    /** @var list<SmithingTransformRecipe> */
    private array $smithingTransformRecipes;

    /** @var list<SmithingTrimRecipe> */
    private array $smithingTrimRecipes;

    /** @var array<string, ContainerMix> */
    private array $containerMixesByInputAndReagent;

    /** @var array<string, PotionMix> */
    private array $potionMixesByInputAndReagent;

    /** @var list<ContainerMix> */
    private array $containerMixes;

    /** @var list<PotionMix> */
    private array $potionMixes;

    /**
     * @param list<RecipeDefinition> $recipes
     * @param list<RecipeDefinition> $craftingTableRecipes
     * @param list<RecipeDefinition> $workstationRecipes
     * @param list<ComplexRecipe> $complexRecipes
     * @param list<RecipeDefinition> $deprecatedRecipes
     * @param array<string, list<RecipeDefinition>> $byIdentifier
     * @param array<string, ComplexRecipe> $byUuid
     * @param array<string, list<RecipeDefinition>> $byStation
     * @param array<string, array<string, list<RecipeDefinition>>> $byStationAndExactInput
     * @param array<string, array<string, list<RecipeDefinition>>> $byStationAndInputTag
     * @param list<SmithingTransformRecipe> $smithingTransformRecipes
     * @param list<SmithingTrimRecipe> $smithingTrimRecipes
     * @param list<ContainerMix> $containerMixes
     * @param list<PotionMix> $potionMixes
     * @param array<string, ContainerMix> $containerMixesByInputAndReagent
     * @param array<string, PotionMix> $potionMixesByInputAndReagent
     */
    private function __construct(
        array $recipes,
        array $craftingTableRecipes,
        array $workstationRecipes,
        array $complexRecipes,
        array $deprecatedRecipes,
        array $byIdentifier,
        array $byUuid,
        array $byStation,
        array $byStationAndExactInput,
        array $byStationAndInputTag,
        array $smithingTransformRecipes,
        array $smithingTrimRecipes,
        array $containerMixes,
        array $potionMixes,
        array $containerMixesByInputAndReagent,
        array $potionMixesByInputAndReagent,
        private RecipeCoverage $coverage,
    ) {
        $this->recipes = $recipes;
        $this->craftingTableRecipes = $craftingTableRecipes;
        $this->workstationRecipes = $workstationRecipes;
        $this->complexRecipes = $complexRecipes;
        $this->deprecatedRecipes = $deprecatedRecipes;
        $this->byIdentifier = $byIdentifier;
        $this->byUuid = $byUuid;
        $this->byStation = $byStation;
        $this->byStationAndExactInput = $byStationAndExactInput;
        $this->byStationAndInputTag = $byStationAndInputTag;
        $this->smithingTransformRecipes = $smithingTransformRecipes;
        $this->smithingTrimRecipes = $smithingTrimRecipes;
        $this->containerMixes = $containerMixes;
        $this->potionMixes = $potionMixes;
        $this->containerMixesByInputAndReagent = $containerMixesByInputAndReagent;
        $this->potionMixesByInputAndReagent = $potionMixesByInputAndReagent;
    }

    public static function fromJson(string $json, ItemNetworkRegistry $items): self
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES || preg_match('//u', $json) !== 1) {
            throw new RuntimeException('Recipe JSON is empty, oversized, or not UTF-8.');
        }
        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Recipes are not valid bounded JSON.', previous: $error);
        }
        if (!is_array($document) || array_is_list($document)
            || !self::hasExactKeys($document, ['containerMixes', 'potionMixes', 'recipes', 'version'])
            || ($document['version'] ?? null) !== BedrockVersion::PROTOCOL
            || !is_array($document['containerMixes']) || !array_is_list($document['containerMixes'])
            || count($document['containerMixes']) > 256
            || !is_array($document['potionMixes']) || !array_is_list($document['potionMixes'])
            || count($document['potionMixes']) > 4_096
            || !is_array($document['recipes']) || !array_is_list($document['recipes'])
            || count($document['recipes']) > self::MAX_RECIPES) {
            throw new RuntimeException('Recipes have an invalid top-level shape, version, or count.');
        }
        [$containerMixes, $potionMixes] = self::parseMixes($document['containerMixes'], $document['potionMixes'], $items);

        $recipes = [];
        $crafting = [];
        $workstations = [];
        $complex = [];
        $deprecated = [];
        $byIdentifier = [];
        $byUuid = [];
        $recipesByStation = [];
        $byStationAndExactInput = [];
        $byStationAndInputTag = [];
        $smithingTransformRecipes = [];
        $smithingTrimRecipes = [];
        $identities = [];
        $byType = [];
        $byStation = [];

        foreach ($document['recipes'] as $index => $data) {
            if (!is_array($data) || array_is_list($data) || !is_int($data['type'] ?? null)) {
                throw new RuntimeException('Recipe contains an invalid record at source index ' . $index . '.');
            }
            try {
                $type = RecipeType::from($data['type']);
            } catch (ValueError $error) {
                throw new RuntimeException('Recipe contains an unknown type at source index ' . $index . '.', previous: $error);
            }
            $recipe = match ($type) {
                RecipeType::SHAPELESS, RecipeType::SHAPELESS_USER_DATA => self::parseShapeless($index, $data, $type, $items),
                RecipeType::SHAPED => self::parseShaped($index, $data, $items),
                RecipeType::COMPLEX => self::parseComplex($index, $data),
                RecipeType::SMITHING_TRANSFORM => self::parseSmithingTransform($index, $data, $items),
                RecipeType::SMITHING_TRIM => self::parseSmithingTrim($index, $data, $items),
            };

            $identity = self::identity($recipe);
            if (isset($identities[$identity])) {
                throw new RuntimeException('Recipe contains a duplicate typed identity at source index ' . $index . '.');
            }
            $identities[$identity] = true;
            $recipes[] = $recipe;
            $byType[$type->value] = ($byType[$type->value] ?? 0) + 1;

            $station = $recipe->station();
            if ($station !== null) {
                $byStation[$station->value] = ($byStation[$station->value] ?? 0) + 1;
                $recipesByStation[$station->value][] = $recipe;
                foreach ($recipe->ingredients() as $ingredient) {
                    $input = $ingredient->itemIdentifier() ?? $ingredient->itemTag();
                    if ($input === null) {
                        throw new RuntimeException('Recipe ingredient has no indexed identity.');
                    }
                    if ($ingredient->type() === RecipeIngredientType::ITEM) {
                        $byStationAndExactInput[$station->value][$input][$recipe->sourceIndex()] = $recipe;
                    } else {
                        $byStationAndInputTag[$station->value][$input][$recipe->sourceIndex()] = $recipe;
                    }
                }
            }
            if ($recipe instanceof SmithingTransformRecipe) {
                $smithingTransformRecipes[] = $recipe;
            } elseif ($recipe instanceof SmithingTrimRecipe) {
                $smithingTrimRecipes[] = $recipe;
            }
            if ($station === RecipeStation::CRAFTING_TABLE) {
                $crafting[] = $recipe;
            } elseif ($station === RecipeStation::DEPRECATED) {
                $deprecated[] = $recipe;
            } elseif ($station !== null) {
                $workstations[] = $recipe;
            } elseif ($recipe instanceof ComplexRecipe) {
                $complex[] = $recipe;
            } else {
                throw new RuntimeException('Recipe was not assigned to a complete classification.');
            }

            $identifier = $recipe->identifier();
            if ($identifier !== null) {
                $byIdentifier[$identifier] ??= [];
                $byIdentifier[$identifier][] = $recipe;
            }
            $uuid = $recipe->uuid();
            if ($uuid !== null) {
                if (isset($byUuid[$uuid]) || !$recipe instanceof ComplexRecipe) {
                    throw new RuntimeException('Recipe contains a duplicate or invalid complex UUID.');
                }
                $byUuid[$uuid] = $recipe;
            }
        }

        $classified = count($crafting) + count($workstations) + count($complex) + count($deprecated);
        if ($classified !== count($recipes)) {
            throw new RuntimeException('Recipe classification did not account for every source record.');
        }
        ksort($byType, SORT_NUMERIC);
        ksort($byStation, SORT_STRING);
        $coverage = new RecipeCoverage(
            count($recipes),
            count($crafting),
            count($workstations),
            count($complex),
            count($deprecated),
            $byType,
            $byStation,
        );

        $exactInputIndex = [];
        foreach ($byStationAndExactInput as $stationValue => $inputs) {
            foreach ($inputs as $input => $indexed) {
                $exactInputIndex[$stationValue][$input] = array_values($indexed);
            }
        }
        $inputTagIndex = [];
        foreach ($byStationAndInputTag as $stationValue => $inputs) {
            foreach ($inputs as $input => $indexed) {
                $inputTagIndex[$stationValue][$input] = array_values($indexed);
            }
        }

        $containerMixIndex = [];
        foreach ($containerMixes as $mix) {
            $key = self::mixKey($mix->inputItemIdentifier(), 0, $mix->reagentItemIdentifier(), 0);
            if (isset($containerMixIndex[$key])) {
                throw new RuntimeException('Container mixes contain a duplicate input and reagent identity.');
            }
            $containerMixIndex[$key] = $mix;
        }
        $potionMixIndex = [];
        foreach ($potionMixes as $mix) {
            $key = self::mixKey($mix->inputItemIdentifier(), $mix->inputMetadata(), $mix->reagentItemIdentifier(), $mix->reagentMetadata());
            if (isset($potionMixIndex[$key])) {
                throw new RuntimeException('Potion mixes contain a duplicate input and reagent identity.');
            }
            $potionMixIndex[$key] = $mix;
        }

        return new self(
            $recipes,
            $crafting,
            $workstations,
            $complex,
            $deprecated,
            $byIdentifier,
            $byUuid,
            $recipesByStation,
            $exactInputIndex,
            $inputTagIndex,
            $smithingTransformRecipes,
            $smithingTrimRecipes,
            $containerMixes,
            $potionMixes,
            $containerMixIndex,
            $potionMixIndex,
            $coverage,
        );
    }

    /** @return list<RecipeDefinition> */
    public function recipes(): array { return $this->recipes; }

    /** @return list<RecipeDefinition> */
    public function craftingTableRecipes(): array { return $this->craftingTableRecipes; }

    /** @return list<RecipeDefinition> */
    public function workstationRecipes(): array { return $this->workstationRecipes; }

    /** @return list<ComplexRecipe> */
    public function complexRecipes(): array { return $this->complexRecipes; }

    /** @return list<RecipeDefinition> */
    public function deprecatedRecipes(): array { return $this->deprecatedRecipes; }

    /** @return list<ContainerMix> */
    public function containerMixes(): array { return $this->containerMixes; }

    /** @return list<PotionMix> */
    public function potionMixes(): array { return $this->potionMixes; }

    public function coverage(): RecipeCoverage { return $this->coverage; }

    /** @return list<RecipeDefinition> */
    public function recipesForIdentifier(string $identifier): array
    {
        return $this->byIdentifier[$identifier] ?? [];
    }

    public function complexRecipe(string $uuid): ComplexRecipe
    {
        return $this->byUuid[strtolower($uuid)] ?? throw new InvalidArgumentException('Complex recipe UUID is not admitted.');
    }

    /** @return list<RecipeDefinition> */
    public function recipesForStation(RecipeStation $station): array
    {
        return $this->byStation[$station->value] ?? [];
    }

    /** @return list<RecipeDefinition> */
    public function recipesForExactInput(RecipeStation $station, string $itemIdentifier): array
    {
        self::assertCanonicalIdentifier($itemIdentifier, 'item identifier');
        return $this->byStationAndExactInput[$station->value][$itemIdentifier] ?? [];
    }

    /** @return list<RecipeDefinition> */
    public function recipesForInputTag(RecipeStation $station, string $itemTag): array
    {
        self::assertCanonicalIdentifier($itemTag, 'item tag');
        return $this->byStationAndInputTag[$station->value][$itemTag] ?? [];
    }

    /** @return list<SmithingTransformRecipe> */
    public function smithingTransformRecipes(): array
    {
        return $this->smithingTransformRecipes;
    }

    /** @return list<SmithingTrimRecipe> */
    public function smithingTrimRecipes(): array
    {
        return $this->smithingTrimRecipes;
    }

    public function containerMix(string $inputItemIdentifier, string $reagentItemIdentifier): ?ContainerMix
    {
        self::assertCanonicalIdentifier($inputItemIdentifier, 'input item identifier');
        self::assertCanonicalIdentifier($reagentItemIdentifier, 'reagent item identifier');
        return $this->containerMixesByInputAndReagent[self::mixKey($inputItemIdentifier, 0, $reagentItemIdentifier, 0)] ?? null;
    }

    public function potionMix(string $inputItemIdentifier, int $inputMetadata, string $reagentItemIdentifier, int $reagentMetadata): ?PotionMix
    {
        self::assertCanonicalIdentifier($inputItemIdentifier, 'input item identifier');
        self::assertCanonicalIdentifier($reagentItemIdentifier, 'reagent item identifier');
        if ($inputMetadata < 0 || $inputMetadata > 32_767 || $reagentMetadata < 0 || $reagentMetadata > 32_767) {
            throw new InvalidArgumentException('Potion metadata must be between 0 and 32767.');
        }
        return $this->potionMixesByInputAndReagent[self::mixKey($inputItemIdentifier, $inputMetadata, $reagentItemIdentifier, $reagentMetadata)] ?? null;
    }

    /** @param array<array-key, mixed> $data */
    private static function parseShapeless(int $index, array $data, RecipeType $type, ItemNetworkRegistry $items): ShapelessRecipe
    {
        if (!self::hasAllowedExactKeys($data, ['block', 'id', 'input', 'output', 'priority', 'type'], ['unlockContext'])
            || !is_array($data['input']) || !array_is_list($data['input'])
            || count($data['input']) < 1 || count($data['input']) > self::MAX_INGREDIENTS
            || !is_array($data['output']) || !array_is_list($data['output'])) {
            throw new RuntimeException('Shapeless recipe has an invalid shape at source index ' . $index . '.');
        }
        $station = self::station($data['block'], $index);
        if ($type === RecipeType::SHAPELESS_USER_DATA && $station !== RecipeStation::CRAFTING_TABLE) {
            throw new RuntimeException('User-data shapeless recipe is assigned to an invalid station.');
        }
        return new ShapelessRecipe(
            $index,
            $type,
            $station,
            self::identifier($data['id'], 'recipe identifier', $index, false),
            self::ingredientList($data['input'], $items, $index),
            self::outputs($data['output'], $items, $index),
            self::priority($data['priority'], $index),
            self::unlockContext($data['unlockContext'] ?? null, $index),
        );
    }

    /** @param array<array-key, mixed> $data */
    private static function parseShaped(int $index, array $data, ItemNetworkRegistry $items): ShapedRecipe
    {
        if (!self::hasAllowedExactKeys($data, ['assumeSymetry', 'block', 'id', 'input', 'output', 'priority', 'shape', 'type'], ['unlockContext'])
            || $data['assumeSymetry'] !== true || !is_array($data['input']) || array_is_list($data['input'])
            || count($data['input']) > self::MAX_INGREDIENTS
            || !is_array($data['shape']) || !array_is_list($data['shape'])
            || !is_array($data['output']) || !array_is_list($data['output'])) {
            throw new RuntimeException('Shaped recipe has an invalid shape at source index ' . $index . '.');
        }
        $shape = self::shape($data['shape'], $data['input'], $index);
        $ingredients = [];
        foreach ($data['input'] as $symbol => $ingredient) {
            if (!is_string($symbol) || strlen($symbol) !== 1 || $symbol === ' ' || !is_array($ingredient)) {
                throw new RuntimeException('Shaped recipe contains an invalid ingredient symbol at source index ' . $index . '.');
            }
            $ingredients[$symbol] = self::ingredient($ingredient, $items, $index);
        }
        return new ShapedRecipe(
            $index,
            self::station($data['block'], $index),
            self::identifier($data['id'], 'recipe identifier', $index, false),
            $shape,
            $ingredients,
            self::outputs($data['output'], $items, $index),
            self::priority($data['priority'], $index),
            true,
            self::unlockContext($data['unlockContext'] ?? null, $index),
        );
    }

    /** @param array<array-key, mixed> $data */
    private static function parseComplex(int $index, array $data): ComplexRecipe
    {
        if (!self::hasExactKeys($data, ['type', 'uuid']) || !is_string($data['uuid'])
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', strtolower($data['uuid'])) !== 1) {
            throw new RuntimeException('Complex recipe has an invalid UUID at source index ' . $index . '.');
        }
        return new ComplexRecipe($index, strtolower($data['uuid']));
    }

    /** @param array<array-key, mixed> $data */
    private static function parseSmithingTransform(int $index, array $data, ItemNetworkRegistry $items): SmithingTransformRecipe
    {
        if (!self::hasExactKeys($data, ['block', 'input', 'output', 'type'])
            || self::station($data['block'], $index) !== RecipeStation::SMITHING_TABLE
            || !is_array($data['input']) || array_is_list($data['input'])
            || !self::hasExactKeys($data['input'], ['addition', 'base', 'template'])
            || !is_array($data['input']['addition']) || !is_array($data['input']['base']) || !is_array($data['input']['template'])
            || !is_array($data['output']) || array_is_list($data['output'])) {
            throw new RuntimeException('Smithing transform recipe has an invalid shape at source index ' . $index . '.');
        }
        return new SmithingTransformRecipe(
            $index,
            self::ingredient($data['input']['template'], $items, $index),
            self::ingredient($data['input']['base'], $items, $index),
            self::ingredient($data['input']['addition'], $items, $index),
            self::output($data['output'], $items, $index),
        );
    }

    /** @param array<array-key, mixed> $data */
    private static function parseSmithingTrim(int $index, array $data, ItemNetworkRegistry $items): SmithingTrimRecipe
    {
        if (!self::hasExactKeys($data, ['block', 'input', 'type'])
            || self::station($data['block'], $index) !== RecipeStation::SMITHING_TABLE
            || !is_array($data['input']) || array_is_list($data['input'])
            || !self::hasExactKeys($data['input'], ['addition', 'base', 'template'])
            || !is_array($data['input']['addition']) || !is_array($data['input']['base']) || !is_array($data['input']['template'])) {
            throw new RuntimeException('Smithing trim recipe has an invalid shape at source index ' . $index . '.');
        }
        return new SmithingTrimRecipe(
            $index,
            self::ingredient($data['input']['template'], $items, $index),
            self::ingredient($data['input']['base'], $items, $index),
            self::ingredient($data['input']['addition'], $items, $index),
        );
    }

    /** @param list<mixed> $values
     *  @return list<RecipeIngredient>
     */
    private static function ingredientList(array $values, ItemNetworkRegistry $items, int $index): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                throw new RuntimeException('Recipe contains a non-object ingredient at source index ' . $index . '.');
            }
            $result[] = self::ingredient($value, $items, $index);
        }
        return $result;
    }

    /** @param array<array-key, mixed> $data */
    private static function ingredient(array $data, ItemNetworkRegistry $items, int $index): RecipeIngredient
    {
        $type = $data['type'] ?? null;
        if ($type === RecipeIngredientType::ITEM->value) {
            if (!self::hasExactKeys($data, ['auxValue', 'count', 'id', 'itemId', 'type'])
                || !is_int($data['count']) || $data['count'] < 1 || $data['count'] > 255
                || !is_int($data['auxValue']) || $data['auxValue'] < 0 || $data['auxValue'] > 32_767
                || !is_int($data['itemId']) || $data['itemId'] < -32_768 || $data['itemId'] > 32_767) {
                throw new RuntimeException('Recipe contains an invalid exact-item ingredient at source index ' . $index . '.');
            }
            $identifier = self::identifier($data['id'], 'ingredient identifier', $index, true);
            self::requireItem($items, $identifier, $index);
            return RecipeIngredient::item($identifier, $data['count'], $data['auxValue'], $data['itemId']);
        }
        if ($type === RecipeIngredientType::TAG->value) {
            if (!self::hasExactKeys($data, ['count', 'itemTag', 'type'])
                || !is_int($data['count']) || $data['count'] < 1 || $data['count'] > 255) {
                throw new RuntimeException('Recipe contains an invalid item-tag ingredient at source index ' . $index . '.');
            }
            return RecipeIngredient::tag(self::identifier($data['itemTag'], 'ingredient tag', $index, true), $data['count']);
        }
        throw new RuntimeException('Recipe contains an unknown ingredient type at source index ' . $index . '.');
    }

    /** @param list<mixed> $values
     *  @return list<RecipeOutput>
     */
    private static function outputs(array $values, ItemNetworkRegistry $items, int $index): array
    {
        if ($values === [] || count($values) > self::MAX_OUTPUTS) {
            throw new RuntimeException('Recipe contains an empty or oversized output list at source index ' . $index . '.');
        }
        $outputs = [];
        foreach ($values as $value) {
            if (!is_array($value) || array_is_list($value)) {
                throw new RuntimeException('Recipe contains a non-object output at source index ' . $index . '.');
            }
            $outputs[] = self::output($value, $items, $index);
        }
        return $outputs;
    }

    /** @param array<array-key, mixed> $data */
    private static function output(array $data, ItemNetworkRegistry $items, int $index): RecipeOutput
    {
        if (!self::hasAllowedExactKeys($data, ['id', 'legacyId'], ['count', 'damage', 'nbt_b64'])
            || !is_int($data['legacyId']) || $data['legacyId'] < -32_768 || $data['legacyId'] > 32_767
            || (isset($data['count']) && (!is_int($data['count']) || $data['count'] < 1 || $data['count'] > 255))
            || (isset($data['damage']) && (!is_int($data['damage']) || $data['damage'] < 0 || $data['damage'] > 32_767))) {
            throw new RuntimeException('Recipe contains an invalid output at source index ' . $index . '.');
        }
        $identifier = self::identifier($data['id'], 'output identifier', $index, true);
        self::requireItem($items, $identifier, $index);
        $nbt = null;
        if (array_key_exists('nbt_b64', $data)) {
            if (!is_string($data['nbt_b64']) || ($nbt = base64_decode($data['nbt_b64'], true)) === false
                || strlen($nbt) > self::MAX_ITEM_NBT_BYTES) {
                throw new RuntimeException('Recipe contains invalid or oversized output NBT at source index ' . $index . '.');
            }
            LittleEndianNbtValidator::rootCompound($nbt, self::MAX_ITEM_NBT_BYTES);
        }
        return new RecipeOutput($identifier, $data['legacyId'], $data['count'] ?? 1, $data['damage'] ?? 0, $nbt);
    }

    /** @param list<mixed> $shape
     *  @param array<array-key, mixed> $ingredients
     *  @return list<string>
     */
    private static function shape(array $shape, array $ingredients, int $index): array
    {
        if ($shape === [] || count($shape) > 3 || !is_string($shape[0] ?? null)) {
            throw new RuntimeException('Shaped recipe has an invalid row count at source index ' . $index . '.');
        }
        $width = strlen($shape[0]);
        if ($width < 1 || $width > 3) {
            throw new RuntimeException('Shaped recipe has an invalid width at source index ' . $index . '.');
        }
        $used = [];
        $validatedShape = [];
        foreach ($shape as $row) {
            if (!is_string($row) || strlen($row) !== $width || preg_match('/^[ -~]{1,3}$/D', $row) !== 1) {
                throw new RuntimeException('Shaped recipe has a non-rectangular or invalid row at source index ' . $index . '.');
            }
            foreach (str_split($row) as $symbol) {
                if ($symbol === ' ') {
                    continue;
                }
                if (!array_key_exists($symbol, $ingredients)) {
                    throw new RuntimeException('Shaped recipe references an undefined symbol at source index ' . $index . '.');
                }
                $used[$symbol] = true;
            }
            $validatedShape[] = $row;
        }
        if ($used === [] || count($used) !== count($ingredients)) {
            throw new RuntimeException('Shaped recipe has unused or missing ingredient symbols at source index ' . $index . '.');
        }
        return $validatedShape;
    }

    private static function station(mixed $value, int $index): RecipeStation
    {
        if (!is_string($value)) {
            throw new RuntimeException('Recipe has no valid station at source index ' . $index . '.');
        }
        try {
            return RecipeStation::from($value);
        } catch (ValueError $error) {
            throw new RuntimeException('Recipe has an unknown station at source index ' . $index . '.', previous: $error);
        }
    }

    private static function priority(mixed $value, int $index): int
    {
        if (!is_int($value) || $value < -1_000 || $value > 1_000) {
            throw new RuntimeException('Recipe has an invalid priority at source index ' . $index . '.');
        }
        return $value;
    }

    private static function unlockContext(mixed $value, int $index): ?RecipeUnlockContext
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException('Recipe has an invalid unlock context at source index ' . $index . '.');
        }
        try {
            return RecipeUnlockContext::from($value);
        } catch (ValueError $error) {
            throw new RuntimeException('Recipe has an unknown unlock context at source index ' . $index . '.', previous: $error);
        }
    }

    private static function identifier(mixed $value, string $label, int $index, bool $canonical): string
    {
        $pattern = $canonical ? '/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D' : '/^[A-Za-z0-9_.:-]+$/D';
        if (!is_string($value) || $value === '' || strlen($value) > 256 || preg_match($pattern, $value) !== 1) {
            throw new RuntimeException(sprintf('Recipe has an invalid %s at source index %d.', $label, $index));
        }
        return $value;
    }

    private static function assertCanonicalIdentifier(string $identifier, string $label): void
    {
        if ($identifier === '' || strlen($identifier) > 256 || preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf('Recipe lookup %s must be canonical.', $label));
        }
    }

    private static function mixKey(string $inputIdentifier, int $inputMetadata, string $reagentIdentifier, int $reagentMetadata): string
    {
        return implode("\0", [$inputIdentifier, (string) $inputMetadata, $reagentIdentifier, (string) $reagentMetadata]);
    }

    private static function requireItem(ItemNetworkRegistry $items, string $identifier, int $index): void
    {
        try {
            $items->definitionForIdentifier($identifier);
        } catch (InvalidArgumentException $error) {
            throw new RuntimeException('Recipe references an unknown item at source index ' . $index . '.', previous: $error);
        }
    }

    private static function identity(RecipeDefinition $recipe): string
    {
        $uuid = $recipe->uuid();
        if ($uuid !== null) {
            return 'uuid:' . $uuid;
        }
        $identifier = $recipe->identifier();
        if ($identifier !== null) {
            $station = $recipe->station();
            if ($station === null) {
                throw new RuntimeException('Named recipe has no station.');
            }
            return implode(':', ['named', (string) $recipe->type()->value, $station->value, $identifier]);
        }
        $station = $recipe->station();
        if ($station === null) {
            throw new RuntimeException('Anonymous non-complex recipe has no station.');
        }
        $parts = [(string) $recipe->type()->value, $station->value];
        foreach ($recipe->ingredients() as $ingredient) {
            $parts[] = implode('|', [
                $ingredient->type()->value,
                $ingredient->itemIdentifier() ?? '',
                $ingredient->itemTag() ?? '',
                (string) $ingredient->count(),
                (string) ($ingredient->auxValue() ?? -1),
            ]);
        }
        foreach ($recipe->outputs() as $output) {
            $parts[] = implode('|', [$output->itemIdentifier(), (string) $output->count(), (string) $output->damage()]);
        }
        return 'anonymous:' . hash('sha256', implode("\0", $parts));
    }

    /** @param list<mixed> $containerMixes
     *  @param list<mixed> $potionMixes
     *  @return array{list<ContainerMix>, list<PotionMix>}
     */
    private static function parseMixes(array $containerMixes, array $potionMixes, ItemNetworkRegistry $items): array
    {
        $parsedContainerMixes = [];
        foreach ($containerMixes as $index => $mix) {
            if (!is_array($mix) || !self::hasExactKeys($mix, ['inputId', 'outputId', 'reagentId'])) {
                throw new RuntimeException('Recipe data contains an invalid container mix at index ' . $index . '.');
            }
            $input = self::identifier($mix['inputId'], 'container mix item', $index, true);
            $output = self::identifier($mix['outputId'], 'container mix item', $index, true);
            $reagent = self::identifier($mix['reagentId'], 'container mix item', $index, true);
            self::requireItem($items, $input, $index);
            self::requireItem($items, $output, $index);
            self::requireItem($items, $reagent, $index);
            $parsedContainerMixes[] = new ContainerMix($input, $reagent, $output);
        }
        $parsedPotionMixes = [];
        foreach ($potionMixes as $index => $mix) {
            if (!is_array($mix) || !self::hasExactKeys($mix, ['inputId', 'inputMeta', 'outputId', 'outputMeta', 'reagentId', 'reagentMeta'])) {
                throw new RuntimeException('Recipe data contains an invalid potion mix at index ' . $index . '.');
            }
            $input = self::identifier($mix['inputId'], 'potion mix item', $index, true);
            $output = self::identifier($mix['outputId'], 'potion mix item', $index, true);
            $reagent = self::identifier($mix['reagentId'], 'potion mix item', $index, true);
            self::requireItem($items, $input, $index);
            self::requireItem($items, $output, $index);
            self::requireItem($items, $reagent, $index);
            foreach (['inputMeta', 'outputMeta', 'reagentMeta'] as $field) {
                if (!is_int($mix[$field]) || $mix[$field] < 0 || $mix[$field] > 32_767) {
                    throw new RuntimeException('Recipe data contains an invalid potion mix metadata value at index ' . $index . '.');
                }
            }
            $parsedPotionMixes[] = new PotionMix(
                $input,
                $mix['inputMeta'],
                $reagent,
                $mix['reagentMeta'],
                $output,
                $mix['outputMeta'],
            );
        }
        return [$parsedContainerMixes, $parsedPotionMixes];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        if (count($data) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $required
     * @param list<string> $optional
     */
    private static function hasAllowedExactKeys(array $data, array $required, array $optional): bool
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || (!in_array($key, $required, true) && !in_array($key, $optional, true))) {
                return false;
            }
        }
        return count($data) >= count($required) && count($data) <= count($required) + count($optional);
    }
}
