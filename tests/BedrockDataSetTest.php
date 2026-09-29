<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use PHPUnit\Framework\TestCase;
use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\BedrockDataSet;
use Bedriox\Data\BedrockVersion;
use Bedriox\Data\CanonicalBlockState;
use Bedriox\Data\LittleEndianNbtValidator;

final class BedrockDataSetTest extends TestCase
{
    public function testActiveBootstrapViewIsLatestOnlyAndSemanticallyCurrent(): void
    {
        $data = BedrockDataSet::bundled();
        $source = AdmittedRegistryDataSet::bundled();

        self::assertSame(2_193, BedrockVersion::PROTOCOL);
        self::assertSame('1.26.52', BedrockVersion::GAME_VERSION);
        self::assertSame('1.26.52', BedrockVersion::QUALIFIED_CLIENT_VERSION);
        self::assertSame([2_193], BedrockVersion::SUPPORTED_PROTOCOLS);
        self::assertSame([
            'air' => 17_013,
            'bedrock' => 17_889,
            'dirt' => 13_444,
            'grass_block' => 14_932,
        ], $data->fixedFlatRuntimeIds());
        self::assertSame($source->fixedFlatRuntimeIds(), $data->fixedFlatRuntimeIds());
        $blocks = $data->blockStateRegistry();
        self::assertCount(22_079, $blocks->states());
        foreach ([
            17_013 => ['minecraft:air', []],
            17_889 => ['minecraft:bedrock', ['infiniburn_bit' => 0]],
            13_444 => ['minecraft:dirt', []],
            14_932 => ['minecraft:grass_block', []],
        ] as $runtimeId => [$identifier, $properties]) {
            $state = $blocks->stateForNetworkRuntimeId($runtimeId);
            self::assertSame($identifier, $state->identifier());
            self::assertSame($properties, $state->properties());
            self::assertSame($runtimeId, $blocks->networkRuntimeId($state));
        }
        $persistentBlocks = $data->persistentBlockStateRegistry();
        self::assertCount(22_079, $persistentBlocks->states());
        $persistentGrass = $persistentBlocks->knownState(CanonicalBlockState::from('minecraft:grass_block'));
        self::assertSame('minecraft:grass_block', $persistentGrass->identifier());
        self::assertSame(18_168_865, $persistentGrass->storageVersion());
        self::assertSame([], $persistentGrass->properties());
        self::assertCount(98, $data->dataDrivenBlockProperties());
        self::assertSame(1, $data->plainsBiomeRuntimeId());
        self::assertSame(hash('sha256', $source->entityIdentifiersNetworkNbt()), hash('sha256', $data->entityIdentifiersNetworkNbt()));

        $items = $data->requiredItems();
        self::assertCount(2_076, $items);
        self::assertCount(78, array_filter($items, static fn(array $item): bool => isset($item['component_nbt'])));
        self::assertSame(
            ['runtime_id' => 837, 'component_based' => true, 'version' => 2],
            array_diff_key($items['minecraft:music_disc_bounce'], ['component_nbt' => true]),
        );
        self::assertSame(-1_125, $items['minecraft:sulfur_spike']['runtime_id']);
        $encodedComponentNbt = $items['minecraft:cooked_cod']['component_nbt'] ?? null;
        self::assertIsString($encodedComponentNbt);
        $componentNbt = base64_decode($encodedComponentNbt, true);
        self::assertIsString($componentNbt);
        LittleEndianNbtValidator::rootCompound($componentNbt, 262_144);
        $itemRegistry = $data->itemNetworkRegistry();
        self::assertCount(2_076, $itemRegistry->definitions());
        self::assertSame(837, $itemRegistry->definitionForIdentifier('minecraft:music_disc_bounce')->networkRuntimeId());
        self::assertSame('minecraft:sulfur_spike', $itemRegistry->definitionForNetworkRuntimeId(-1_125)->identifier());
        self::assertCount(4_768, $data->recipeRegistry()->recipes());

        $biomes = $data->biomeDefinitions();
        self::assertCount(89, $biomes);
        self::assertContains('minecraft:dappled_forest', array_column($biomes, 'name'));
        self::assertContains('minecraft:sulfur_caves', array_column($biomes, 'name'));
        $biomeRuntimeIds = $data->biomeRuntimeIds();
        self::assertCount(89, $biomeRuntimeIds);
        self::assertSame(0, $biomeRuntimeIds['minecraft:ocean']);
        self::assertSame(1, $biomeRuntimeIds['minecraft:plains']);
        self::assertSame(24, $biomeRuntimeIds['minecraft:deep_ocean']);
        self::assertSame(195, $biomeRuntimeIds['minecraft:dappled_forest']);
    }
}
