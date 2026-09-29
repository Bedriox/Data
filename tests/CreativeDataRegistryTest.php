<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Bedriox\Data\BedrockDataSet;
use Bedriox\Data\BlockItemMappingRegistry;
use Bedriox\Data\BlockPropertyRegistry;
use Bedriox\Data\CanonicalBlockState;
use Bedriox\Data\CreativeInventoryCategory;
use Bedriox\Data\CreativeInventoryRegistry;
use Bedriox\Data\VoxelShapeRegistry;

final class CreativeDataRegistryTest extends TestCase
{
    public function testCreativeRegistryPreservesOrderGroupsAndRepeatedItemVariants(): void
    {
        $data = BedrockDataSet::bundled();
        $minimalNbt = "\x0a\x00\x00\x00";
        $registry = CreativeInventoryRegistry::fromJson(self::json([
            'schema' => 1,
            'groups' => [[
                'id' => 7,
                'category' => 'construction',
                'name' => 'itemGroup.name.stone',
                'icon' => self::item('minecraft:stone', CanonicalBlockState::from('minecraft:stone')),
            ]],
            'entries' => [
                [
                    'creativeNetworkId' => 11,
                    'category' => 'construction',
                    'groupId' => 7,
                    'item' => self::item('minecraft:stone', CanonicalBlockState::from('minecraft:stone')),
                ],
                [
                    'creativeNetworkId' => 12,
                    'category' => 'construction',
                    'groupId' => 7,
                    'item' => self::item('minecraft:stone', CanonicalBlockState::from('minecraft:stone'), 3, $minimalNbt),
                ],
            ],
        ]), $data->itemNetworkRegistry(), $data->blockStateRegistry());

        self::assertCount(1, $registry->groups());
        self::assertCount(2, $registry->entries());
        self::assertSame(CreativeInventoryCategory::Construction, $registry->group(7)->category());
        self::assertSame('itemGroup.name.stone', $registry->group(7)->name());
        self::assertSame('minecraft:stone', $registry->group(7)->icon()->identifier());
        self::assertSame([11, 12], array_map(static fn($entry): int => $entry->creativeNetworkId(), $registry->entries()));
        self::assertSame('minecraft:stone', $registry->entry(11)->item()->identifier());
        self::assertSame(3, $registry->entry(12)->item()->damage());
        self::assertSame($minimalNbt, $registry->entry(12)->item()->nbt());
    }

    public function testCreativeRegistryRejectsUnknownReferencesMalformedNbtAndCrossCategoryGroups(): void
    {
        $data = BedrockDataSet::bundled();
        $base = [
            'schema' => 1,
            'groups' => [[
                'id' => 0,
                'category' => 'construction',
                'name' => 'group',
                'icon' => self::item('minecraft:stone'),
            ]],
            'entries' => [[
                'creativeNetworkId' => 1,
                'category' => 'construction',
                'groupId' => 0,
                'item' => self::item('minecraft:stone'),
            ]],
        ];
        $cases = [];
        $unknownItem = $base;
        $unknownItem['entries'][0]['item']['identifier'] = 'minecraft:not_admitted';
        $cases[] = $unknownItem;
        $unknownState = $base;
        $unknownState['entries'][0]['item']['blockState'] = ['identifier' => 'minecraft:not_admitted', 'properties' => []];
        $cases[] = $unknownState;
        $malformedNbt = $base;
        $malformedNbt['entries'][0]['item']['nbt'] = base64_encode('invalid');
        $cases[] = $malformedNbt;
        $crossCategory = $base;
        $crossCategory['entries'][0]['category'] = 'items';
        $cases[] = $crossCategory;
        $duplicateId = $base;
        $duplicateId['entries'][] = $duplicateId['entries'][0];
        $cases[] = $duplicateId;

        foreach ($cases as $case) {
            try {
                CreativeInventoryRegistry::fromJson(self::json($case), $data->itemNetworkRegistry(), $data->blockStateRegistry());
                self::fail('Invalid creative inventory data was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testBlockItemPropertiesAndVoxelRegistriesCrossValidatePaletteReferences(): void
    {
        $data = BedrockDataSet::bundled();
        $blocks = $data->blockStateRegistry();
        $items = $data->itemNetworkRegistry();
        $mapping = BlockItemMappingRegistry::fromJson(self::json([
            'schema' => 1,
            'mappings' => [
                ['itemIdentifier' => 'minecraft:stone', 'blockState' => self::state(CanonicalBlockState::from('minecraft:stone'))],
                ['itemIdentifier' => 'minecraft:dirt', 'blockState' => self::state(CanonicalBlockState::from('minecraft:dirt'))],
            ],
        ]), $items, $blocks);
        self::assertSame('minecraft:stone', $mapping->mappingForBlockState(CanonicalBlockState::from('minecraft:stone'))->itemIdentifier());
        self::assertSame('minecraft:dirt', $mapping->mappingForItem('minecraft:dirt')->blockState()->identifier());

        $properties = BlockPropertyRegistry::fromJson(self::json([
            'schema' => 1,
            'states' => [self::physicalProperties(CanonicalBlockState::from('minecraft:stone'))],
        ]), $blocks);
        self::assertSame(1.5, $properties->propertiesForState(CanonicalBlockState::from('minecraft:stone'))->hardness());
        self::assertCount(1, $properties->properties());

        $voxels = VoxelShapeRegistry::fromJson(self::json([
            'schema' => 1,
            'shapes' => [['id' => 9, 'boxes' => [['min' => [0, 0, 0], 'max' => [1, 1, 1]]]]],
            'assignments' => [['shapeId' => 9, 'blockState' => self::state(CanonicalBlockState::from('minecraft:stone'))]],
        ]), $blocks);
        self::assertSame([[0.0, 0.0, 0.0], [1.0, 1.0, 1.0]], [
            $voxels->shapeForBlockState(CanonicalBlockState::from('minecraft:stone'))->boxes()[0]->minimum(),
            $voxels->shape(9)->boxes()[0]->maximum(),
        ]);
    }

    public function testMetadataRegistriesRejectUnknownDuplicateAndOutOfRangeInput(): void
    {
        $data = BedrockDataSet::bundled();
        $items = $data->itemNetworkRegistry();
        $blocks = $data->blockStateRegistry();
        foreach ([
            ['schema' => 1, 'mappings' => [['itemIdentifier' => 'minecraft:not_admitted', 'blockState' => self::state(CanonicalBlockState::from('minecraft:stone'))]]],
            ['schema' => 1, 'mappings' => [
                ['itemIdentifier' => 'minecraft:stone', 'blockState' => self::state(CanonicalBlockState::from('minecraft:stone'))],
                ['itemIdentifier' => 'minecraft:stone', 'blockState' => self::state(CanonicalBlockState::from('minecraft:dirt'))],
            ]],
        ] as $case) {
            try {
                BlockItemMappingRegistry::fromJson(self::json($case), $items, $blocks);
                self::fail('Invalid block-item mapping was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(RuntimeException::class);
        VoxelShapeRegistry::fromJson(self::json([
            'schema' => 1,
            'shapes' => [['id' => 1, 'boxes' => [['min' => [0, 0, 0], 'max' => [17, 1, 1]]]]],
            'assignments' => [],
        ]), $blocks);
    }

    public function testUnknownLookupsFailClosed(): void
    {
        $data = BedrockDataSet::bundled();
        $registry = CreativeInventoryRegistry::fromJson(self::json([
            'schema' => 1,
            'groups' => [],
            'entries' => [[
                'creativeNetworkId' => 1,
                'category' => 'items',
                'groupId' => null,
                'item' => self::item('minecraft:stick'),
            ]],
        ]), $data->itemNetworkRegistry(), $data->blockStateRegistry());

        $this->expectException(InvalidArgumentException::class);
        $registry->entry(2);
    }

    /** @return array{identifier: string, count: int, damage: int, blockState: array{identifier: string, properties: array<string, int|string>}|null, nbt: string|null} */
    private static function item(string $identifier, ?CanonicalBlockState $state = null, int $damage = 0, ?string $nbt = null): array
    {
        return [
            'identifier' => $identifier,
            'count' => 1,
            'damage' => $damage,
            'blockState' => $state === null ? null : self::state($state),
            'nbt' => $nbt === null ? null : base64_encode($nbt),
        ];
    }

    /** @return array{identifier: string, properties: array<string, int|string>} */
    private static function state(CanonicalBlockState $state): array
    {
        return ['identifier' => $state->identifier(), 'properties' => $state->properties()];
    }

    /** @return array<string, mixed> */
    private static function physicalProperties(CanonicalBlockState $state): array
    {
        return [
            'blockState' => self::state($state),
            'burnOdds' => 0,
            'canContainLiquidSource' => false,
            'collisionShape' => [[0, 0, 0, 1, 1, 1]],
            'explosionResistance' => 1.5,
            'flameOdds' => 0,
            'friction' => 0.6,
            'hardness' => 1.5,
            'isSolid' => true,
            'lightDampening' => 15,
            'lightEmission' => 0,
            'liquidClipShape' => [0, 0, 0, 0, -34_771_968, 0],
            'liquidReactionOnTouch' => 'BLOCKING',
            'mapColor' => '#000000ff',
            'outlineShape' => [0, 0, 0, 1, 1, 1],
            'requiresCorrectToolForDrops' => false,
            'thickness' => 0,
            'tintMethod' => 'None',
            'translationKey' => 'tile.synthetic.name',
            'translucency' => 0,
            'uiShape' => [0, 0, 0, 1, 1, 1],
            'visualShape' => [0, 0, 0, 1, 1, 1],
        ];
    }

    /** @param array<array-key, mixed> $data */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
