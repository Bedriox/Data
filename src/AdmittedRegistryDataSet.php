<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** @internal Immutable, hash-verified access to the pinned registry source artifacts. */
final readonly class AdmittedRegistryDataSet
{
    /** Canonical vanilla biome runtime IDs used by LevelChunk biome palettes. */
    private const array BIOME_RUNTIME_IDS = [
        'minecraft:ocean' => 0,
        'minecraft:plains' => 1,
        'minecraft:desert' => 2,
        'minecraft:extreme_hills' => 3,
        'minecraft:forest' => 4,
        'minecraft:taiga' => 5,
        'minecraft:swampland' => 6,
        'minecraft:river' => 7,
        'minecraft:hell' => 8,
        'minecraft:the_end' => 9,
        'minecraft:legacy_frozen_ocean' => 10,
        'minecraft:frozen_river' => 11,
        'minecraft:ice_plains' => 12,
        'minecraft:ice_mountains' => 13,
        'minecraft:mushroom_island' => 14,
        'minecraft:mushroom_island_shore' => 15,
        'minecraft:beach' => 16,
        'minecraft:desert_hills' => 17,
        'minecraft:forest_hills' => 18,
        'minecraft:taiga_hills' => 19,
        'minecraft:extreme_hills_edge' => 20,
        'minecraft:jungle' => 21,
        'minecraft:jungle_hills' => 22,
        'minecraft:jungle_edge' => 23,
        'minecraft:deep_ocean' => 24,
        'minecraft:stone_beach' => 25,
        'minecraft:cold_beach' => 26,
        'minecraft:birch_forest' => 27,
        'minecraft:birch_forest_hills' => 28,
        'minecraft:roofed_forest' => 29,
        'minecraft:cold_taiga' => 30,
        'minecraft:cold_taiga_hills' => 31,
        'minecraft:mega_taiga' => 32,
        'minecraft:mega_taiga_hills' => 33,
        'minecraft:extreme_hills_plus_trees' => 34,
        'minecraft:savanna' => 35,
        'minecraft:savanna_plateau' => 36,
        'minecraft:mesa' => 37,
        'minecraft:mesa_plateau_stone' => 38,
        'minecraft:mesa_plateau' => 39,
        'minecraft:warm_ocean' => 40,
        'minecraft:deep_warm_ocean' => 41,
        'minecraft:lukewarm_ocean' => 42,
        'minecraft:deep_lukewarm_ocean' => 43,
        'minecraft:cold_ocean' => 44,
        'minecraft:deep_cold_ocean' => 45,
        'minecraft:frozen_ocean' => 46,
        'minecraft:deep_frozen_ocean' => 47,
        'minecraft:bamboo_jungle' => 48,
        'minecraft:bamboo_jungle_hills' => 49,
        'minecraft:sunflower_plains' => 129,
        'minecraft:desert_mutated' => 130,
        'minecraft:extreme_hills_mutated' => 131,
        'minecraft:flower_forest' => 132,
        'minecraft:taiga_mutated' => 133,
        'minecraft:swampland_mutated' => 134,
        'minecraft:ice_plains_spikes' => 140,
        'minecraft:jungle_mutated' => 149,
        'minecraft:jungle_edge_mutated' => 151,
        'minecraft:birch_forest_mutated' => 155,
        'minecraft:birch_forest_hills_mutated' => 156,
        'minecraft:roofed_forest_mutated' => 157,
        'minecraft:cold_taiga_mutated' => 158,
        'minecraft:redwood_taiga_mutated' => 160,
        'minecraft:redwood_taiga_hills_mutated' => 161,
        'minecraft:extreme_hills_plus_trees_mutated' => 162,
        'minecraft:savanna_mutated' => 163,
        'minecraft:savanna_plateau_mutated' => 164,
        'minecraft:mesa_bryce' => 165,
        'minecraft:mesa_plateau_stone_mutated' => 166,
        'minecraft:mesa_plateau_mutated' => 167,
        'minecraft:soulsand_valley' => 178,
        'minecraft:crimson_forest' => 179,
        'minecraft:warped_forest' => 180,
        'minecraft:basalt_deltas' => 181,
        'minecraft:jagged_peaks' => 182,
        'minecraft:frozen_peaks' => 183,
        'minecraft:snowy_slopes' => 184,
        'minecraft:grove' => 185,
        'minecraft:meadow' => 186,
        'minecraft:lush_caves' => 187,
        'minecraft:dripstone_caves' => 188,
        'minecraft:stony_peaks' => 189,
        'minecraft:deep_dark' => 190,
        'minecraft:mangrove_swamp' => 191,
        'minecraft:cherry_grove' => 192,
        'minecraft:pale_garden' => 193,
        'minecraft:sulfur_caves' => 194,
        'minecraft:dappled_forest' => 195,
    ];

    public const int MAX_ARTIFACT_BYTES = 1_000_000;

    private function __construct(
        private string $rootDirectory,
        private AdmittedDataManifest $manifest,
        private ?string $compatibleArtifactDirectory,
    ) {}

    public static function bundled(): self
    {
        return new self(dirname(__DIR__), AdmittedDataManifest::bundled(), null);
    }

    public static function fromDirectory(string $directory): self
    {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Artifact directory must be a non-empty path without NUL bytes.');
        }
        $resolved = realpath($directory);
        if (!is_string($resolved) || !is_dir($resolved)) {
            throw new InvalidArgumentException('Artifact directory must resolve to an existing directory.');
        }
        return new self(dirname(__DIR__), AdmittedDataManifest::bundled(), rtrim($resolved, '/\\'));
    }

    /** @return list<string> */
    public static function artifactNames(): array
    {
        return array_keys(AdmittedDataManifest::bundled()->artifacts());
    }

    public function artifact(string $name): string
    {
        $record = $this->manifest->artifact($name);
        $relativePath = $this->compatibleArtifactDirectory === null
            ? str_replace('/', DIRECTORY_SEPARATOR, $record->path())
            : basename($record->path());
        $path = ($this->compatibleArtifactDirectory ?? $this->rootDirectory) . DIRECTORY_SEPARATOR . $relativePath;
        $resolved = realpath($path);
        if (is_link($path) || !is_string($resolved) || $resolved !== $path) {
            throw new RuntimeException('Admitted registry artifact must be a direct, non-symlink file in the dataset directory.');
        }
        $size = @filesize($path);
        if (!is_int($size) || $size !== $record->size()) {
            throw new RuntimeException('Admitted registry artifact size is missing or does not match its admission record.');
        }
        $contents = @file_get_contents($path, false, null, 0, $record->size() + 1);
        if (!is_string($contents) || strlen($contents) !== $record->size() || hash('sha256', $contents) !== $record->sha256()) {
            throw new RuntimeException('Admitted registry artifact content does not match its admitted SHA-256.');
        }
        return $contents;
    }

    /** @return array<string, array{runtime_id: int, component_based: bool, version: int, component_nbt?: string}> */
    public function requiredItems(): array
    {
        $decoded = json_decode($this->artifact('runtime_item_states'), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > 5_000) {
            throw new RuntimeException('Required-item artifact has an invalid top-level shape or count.');
        }
        $components = BigEndianNbtRegistry::itemComponents($this->artifact('item_components'));
        $items = [];
        $runtimeIds = [];
        foreach ($decoded as $entry) {
            $identifier = is_array($entry) ? ($entry['name'] ?? null) : null;
            $runtimeId = is_array($entry) ? ($entry['id'] ?? null) : null;
            $componentBased = is_array($entry) ? ($entry['componentBased'] ?? null) : null;
            if (!is_string($identifier) || preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || !is_array($entry)
                || !is_int($runtimeId) || !is_bool($componentBased) || isset($items[$identifier]) || isset($runtimeIds[$runtimeId])
                || $runtimeId < -32_768 || $runtimeId > 32_767
                || !is_int($entry['version'] ?? null) || $entry['version'] < 0 || $entry['version'] > 2
                || count($entry) !== 4) {
                throw new RuntimeException('Required-item artifact contains an invalid entry.');
            }
            $item = [
                'runtime_id' => $runtimeId,
                'component_based' => $componentBased,
                'version' => $entry['version'],
            ];
            if (isset($components[$identifier])) {
                LittleEndianNbtValidator::rootCompound($components[$identifier], 262_144);
                $item['component_nbt'] = base64_encode($components[$identifier]);
            }
            $items[$identifier] = $item;
            $runtimeIds[$runtimeId] = true;
        }
        foreach ($components as $identifier => $_component) {
            if (!isset($items[$identifier])) {
                throw new RuntimeException('Item-component artifact contains an unknown non-empty item component.');
            }
        }
        return $items;
    }

    public function itemNetworkRegistry(): ItemNetworkRegistry
    {
        return ItemNetworkRegistry::fromRequiredItems($this->requiredItems());
    }

    public function recipeRegistry(): RecipeRegistry
    {
        return RecipeRegistry::fromJson($this->artifact('recipes'), $this->itemNetworkRegistry());
    }

    public function creativeInventoryRegistry(): CreativeInventoryRegistry
    {
        return CreativeInventoryRegistry::fromJson(
            $this->artifact('creative_inventory'),
            $this->itemNetworkRegistry(),
            $this->blockStateRegistry(),
        );
    }

    public function blockItemMappingRegistry(): BlockItemMappingRegistry
    {
        return BlockItemMappingRegistry::fromJson(
            $this->artifact('block_item_mappings'),
            $this->itemNetworkRegistry(),
            $this->blockStateRegistry(),
        );
    }

    public function blockPropertyRegistry(): BlockPropertyRegistry
    {
        $blocks = $this->blockStateRegistry();
        $registry = BlockPropertyRegistry::fromJson($this->artifact('block_properties'), $blocks);
        $states = $blocks->states();
        if (count($registry->properties()) !== count($states)) {
            throw new RuntimeException('Admitted block properties do not cover the complete block-state palette.');
        }
        foreach ($registry->properties() as $index => $properties) {
            if ($properties->blockState()->canonicalKey() !== $states[$index]->canonicalKey()) {
                throw new RuntimeException('Admitted block-property order does not match the block-state palette.');
            }
        }
        return $registry;
    }

    public function voxelShapeRegistry(): VoxelShapeRegistry
    {
        $blocks = $this->blockStateRegistry();
        $registry = VoxelShapeRegistry::fromJson($this->artifact('voxel_shapes'), $blocks);
        if ($registry->assignmentCount() !== count($blocks->states())) {
            throw new RuntimeException('Admitted voxel shapes do not assign the complete block-state palette.');
        }
        return $registry;
    }

    /** @return array{air: int, bedrock: int, dirt: int, grass_block: int} */
    public function fixedFlatRuntimeIds(): array
    {
        return BigEndianNbtRegistry::fixedFlatRuntimeIds($this->artifact('block_palette'));
    }

    public function blockStateRegistry(): NetworkBlockStateRegistry
    {
        return NetworkBlockStateRegistry::fromAdmittedPalette($this->artifact('block_palette'));
    }

    public function persistentBlockStateRegistry(): PersistentBlockStateRegistry
    {
        return PersistentBlockStateRegistry::fromAdmittedPalette($this->artifact('block_palette'));
    }

    /** @return array<string, string> identifier => little-endian named compound */
    public function dataDrivenBlockProperties(): array
    {
        $properties = BigEndianNbtRegistry::dataDrivenBlockProperties($this->artifact('data_driven_blocks'));
        if ($properties === [] || count($properties) > 1_024) {
            throw new RuntimeException('Data-driven block-property artifact has an invalid entry count.');
        }
        foreach ($properties as $identifier => $nbt) {
            if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
                throw new RuntimeException('Data-driven block-property artifact contains an invalid identifier.');
            }
            LittleEndianNbtValidator::rootCompound($nbt, 262_144);
        }
        return $properties;
    }

    /**
     * @return list<array{name: string, id: int, temperature: float, downfall: float, foliage_snow: float,
     *     depth: float, scale: float, map_water_argb: int, rain: bool, tags: list<string>}>
     */
    public function biomeDefinitions(): array
    {
        $decoded = json_decode($this->artifact('biome_definitions'), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || $decoded === [] || count($decoded) > 1_024 || array_is_list($decoded)) {
            throw new RuntimeException('Biome-definition artifact has an invalid top-level shape or count.');
        }
        $result = [];
        $names = [];
        foreach ($decoded as $name => $entry) {
            if (!is_string($name) || preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $name) !== 1 || !is_array($entry)
                || isset($names[$name]) || ($entry['id'] ?? null) !== null
                || !is_bool($entry['rain'] ?? null) || !is_array($entry['mapWaterColor'] ?? null)
                || !is_array($entry['tags'] ?? null) || !array_is_list($entry['tags']) || count($entry['tags']) > 128) {
                throw new RuntimeException('Biome-definition artifact contains an invalid entry.');
            }
            $floats = [];
            foreach (['temperature', 'downfall', 'foliageSnow', 'depth', 'scale'] as $field) {
                $value = $entry[$field] ?? null;
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                    throw new RuntimeException('Biome-definition artifact contains an invalid numeric field.');
                }
                $floats[$field] = (float) $value;
            }
            $color = $entry['mapWaterColor'];
            foreach (['a', 'r', 'g', 'b'] as $channel) {
                if (!is_int($color[$channel] ?? null) || $color[$channel] < 0 || $color[$channel] > 255) {
                    throw new RuntimeException('Biome-definition artifact contains an invalid colour channel.');
                }
            }
            $tags = [];
            foreach ($entry['tags'] as $tag) {
                if (!is_string($tag) || strlen($tag) > 128 || preg_match('//u', $tag) !== 1) {
                    throw new RuntimeException('Biome-definition artifact contains an invalid tag.');
                }
                $tags[] = $tag;
            }
            $result[] = [
                'name' => $name,
                'id' => count($result),
                'temperature' => $floats['temperature'],
                'downfall' => $floats['downfall'],
                'foliage_snow' => $floats['foliageSnow'],
                'depth' => $floats['depth'],
                'scale' => $floats['scale'],
                'map_water_argb' => ($color['a'] << 24) | ($color['r'] << 16) | ($color['g'] << 8) | $color['b'],
                'rain' => $entry['rain'],
                'tags' => $tags,
            ];
            $names[$name] = true;
        }
        return $result;
    }

    /** @return array<string, int> canonical biome identifier => LevelChunk runtime ID */
    public function biomeRuntimeIds(): array
    {
        $definitions = $this->biomeDefinitions();
        $names = array_column($definitions, 'name');
        $runtimeNames = array_keys(self::BIOME_RUNTIME_IDS);
        if (count($names) !== count($runtimeNames)
            || array_diff($names, $runtimeNames) !== [] || array_diff($runtimeNames, $names) !== []
            || count(array_unique(self::BIOME_RUNTIME_IDS, SORT_REGULAR)) !== count(self::BIOME_RUNTIME_IDS)) {
            throw new RuntimeException('Biome definitions do not match the admitted LevelChunk runtime-ID registry.');
        }

        return self::BIOME_RUNTIME_IDS;
    }

    public function plainsBiomeRuntimeId(): int
    {
        return $this->biomeRuntimeIds()['minecraft:plains'];
    }

    public function entityIdentifiersNetworkNbt(): string
    {
        $nbt = $this->artifact('entity_identifiers');
        CanonicalBlockStateIndex::validateRootCompound($nbt, 65_536);
        return $nbt;
    }

    public function entityTypeRegistry(): EntityTypeRegistry
    {
        return EntityTypeRegistry::fromAdmittedArtifacts(
            $this->entityIdentifiersNetworkNbt(),
            $this->artifact('entity_properties'),
            $this->itemNetworkRegistry(),
        );
    }
}
