<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Active protocol-2193 bootstrap view over the admitted Bedrock registries. */
final readonly class BedrockDataSet
{
    private function __construct(private AdmittedRegistryDataSet $registries) {}

    public static function bundled(): self
    {
        return new self(AdmittedRegistryDataSet::bundled());
    }

    /** @return array<string, array{runtime_id: int, component_based: bool, version: int, component_nbt?: string}> */
    public function requiredItems(): array
    {
        return $this->registries->requiredItems();
    }

    public function itemNetworkRegistry(): ItemNetworkRegistry
    {
        return $this->registries->itemNetworkRegistry();
    }

    public function recipeRegistry(): RecipeRegistry
    {
        return $this->registries->recipeRegistry();
    }

    public function creativeInventoryRegistry(): CreativeInventoryRegistry
    {
        return $this->registries->creativeInventoryRegistry();
    }

    public function blockItemMappingRegistry(): BlockItemMappingRegistry
    {
        return $this->registries->blockItemMappingRegistry();
    }

    public function blockPropertyRegistry(): BlockPropertyRegistry
    {
        return $this->registries->blockPropertyRegistry();
    }

    public function voxelShapeRegistry(): VoxelShapeRegistry
    {
        return $this->registries->voxelShapeRegistry();
    }

    /** @return array{air: int, bedrock: int, dirt: int, grass_block: int} */
    public function fixedFlatRuntimeIds(): array
    {
        return $this->registries->fixedFlatRuntimeIds();
    }

    public function blockStateRegistry(): NetworkBlockStateRegistry
    {
        return $this->registries->blockStateRegistry();
    }

    public function persistentBlockStateRegistry(): PersistentBlockStateRegistry
    {
        return $this->registries->persistentBlockStateRegistry();
    }

    /** @return array<string, string> identifier => little-endian named compound */
    public function dataDrivenBlockProperties(): array
    {
        return $this->registries->dataDrivenBlockProperties();
    }

    /**
     * @return list<array{name: string, id: int, temperature: float, downfall: float, foliage_snow: float,
     *     depth: float, scale: float, map_water_argb: int, rain: bool, tags: list<string>}>
     */
    public function biomeDefinitions(): array
    {
        return $this->registries->biomeDefinitions();
    }

    /** @return array<string, int> canonical biome identifier => LevelChunk runtime ID */
    public function biomeRuntimeIds(): array
    {
        return $this->registries->biomeRuntimeIds();
    }

    public function plainsBiomeRuntimeId(): int
    {
        return $this->registries->plainsBiomeRuntimeId();
    }

    public function entityIdentifiersNetworkNbt(): string
    {
        return $this->registries->entityIdentifiersNetworkNbt();
    }

    public function entityTypeRegistry(): EntityTypeRegistry
    {
        return $this->registries->entityTypeRegistry();
    }
}
