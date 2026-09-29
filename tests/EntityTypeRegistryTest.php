<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use Bedriox\Data\BedrockDataSet;
use Bedriox\Data\EntityPropertyType;
use Bedriox\Data\EntityTypeRegistry;
use Bedriox\Data\ItemNetworkRegistry;
use Bedriox\Data\NetworkEntityIdentifierReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EntityTypeRegistryTest extends TestCase
{
    public function testBundledCatalogHasExactCurrentCountsMappingsAndSemanticHash(): void
    {
        $data = BedrockDataSet::bundled();
        $registry = $data->entityTypeRegistry();
        $definitions = $registry->definitions();

        self::assertCount(137, $definitions);
        self::assertCount(112, array_filter($definitions, static fn($definition): bool => $definition->summonable()));
        self::assertCount(89, array_filter(
            $definitions,
            static fn($definition): bool => $definition->spawnEggAdvertised(),
        ));
        self::assertCount(90, $registry->spawnEggMappings());
        self::assertSame(
            'dbc19d3ccfdeeb21a5d36006cac31a43e5b412ac3c04856656d8f1caed2908ff',
            $registry->catalogSha256(),
        );

        $runtimeIds = array_map(static fn($definition): int => $definition->networkRuntimeId(), $definitions);
        self::assertCount(count($runtimeIds), array_unique($runtimeIds, SORT_REGULAR));
        foreach ($definitions as $identifier => $definition) {
            self::assertSame($identifier, $definition->identifier());
            self::assertSame($definition, $registry->definitionForNetworkRuntimeId($definition->networkRuntimeId()));
        }
    }

    public function testEveryAdmittedSpawnEggResolvesBijectivelyToOneEntity(): void
    {
        $data = BedrockDataSet::bundled();
        $registry = $data->entityTypeRegistry();
        $itemSpawnEggs = [];
        foreach ($data->itemNetworkRegistry()->definitions() as $item) {
            if (str_starts_with($item->identifier(), 'minecraft:')
                && str_ends_with($item->identifier(), '_spawn_egg')) {
                $itemSpawnEggs[] = $item->identifier();
            }
        }
        sort($itemSpawnEggs, SORT_STRING);
        $mappedSpawnEggs = array_keys($registry->spawnEggMappings());
        sort($mappedSpawnEggs, SORT_STRING);
        self::assertSame($itemSpawnEggs, $mappedSpawnEggs);

        $mappedEntities = [];
        foreach ($registry->spawnEggMappings() as $itemIdentifier => $entity) {
            self::assertSame($entity, $registry->entityForSpawnEgg($itemIdentifier));
            self::assertSame($itemIdentifier, $entity->spawnEggItemIdentifier());
            self::assertArrayNotHasKey($entity->identifier(), $mappedEntities);
            $mappedEntities[$entity->identifier()] = true;
        }
        self::assertCount(90, $mappedEntities);

        self::assertSame('minecraft:agent', $registry->entityForSpawnEgg('minecraft:agent_spawn_egg')->identifier());
        self::assertSame(
            'minecraft:evocation_illager',
            $registry->entityForSpawnEgg('minecraft:evoker_spawn_egg')->identifier(),
        );
        self::assertSame(
            'minecraft:tropicalfish',
            $registry->entityForSpawnEgg('minecraft:tropical_fish_spawn_egg')->identifier(),
        );
        self::assertSame(
            'minecraft:villager_v2',
            $registry->entityForSpawnEgg('minecraft:villager_spawn_egg')->identifier(),
        );
        self::assertSame(
            'minecraft:zombie_villager_v2',
            $registry->entityForSpawnEgg('minecraft:zombie_villager_spawn_egg')->identifier(),
        );
    }

    public function testAdmittedEntityPropertiesAreTypedAndBounded(): void
    {
        $definitions = BedrockDataSet::bundled()->entityTypeRegistry()->definitions();
        $owners = array_filter($definitions, static fn($definition): bool => $definition->properties() !== []);
        self::assertCount(13, $owners);
        self::assertSame(19, array_sum(array_map(
            static fn($definition): int => count($definition->properties()),
            $owners,
        )));

        $creaking = BedrockDataSet::bundled()->entityTypeRegistry()->definitionForIdentifier('minecraft:creaking');
        $swaying = array_values(array_filter(
            $creaking->properties(),
            static fn($property): bool => $property->identifier() === 'minecraft:creaking_swaying_ticks',
        ))[0] ?? null;
        self::assertNotNull($swaying);
        self::assertSame(EntityPropertyType::INTEGER, $swaying->type());
        self::assertSame(0, $swaying->minimum());
        self::assertSame(6, $swaying->maximum());

        $bee = BedrockDataSet::bundled()->entityTypeRegistry()->definitionForIdentifier('minecraft:bee');
        self::assertSame(EntityPropertyType::BOOLEAN, $bee->properties()[0]->type());
        $wolf = BedrockDataSet::bundled()->entityTypeRegistry()->definitionForIdentifier('minecraft:wolf');
        self::assertSame(
            ['default', 'big', 'cute', 'grumpy', 'mad', 'puglin', 'sad'],
            $wolf->properties()[0]->enumValues(),
        );
    }

    public function testUnknownLookupsFailClosed(): void
    {
        $registry = BedrockDataSet::bundled()->entityTypeRegistry();
        foreach ([
            static fn() => $registry->definitionForIdentifier('minecraft:not_admitted'),
            static fn() => $registry->definitionForNetworkRuntimeId(0x7fffffff),
            static fn() => $registry->entityForSpawnEgg('minecraft:not_admitted_spawn_egg'),
        ] as $lookup) {
            try {
                $lookup();
                self::fail('Unknown entity catalog lookup was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testIdentifierDecoderRejectsMissingUnknownDuplicateAndOversizedShapes(): void
    {
        $valid = self::networkIdentifiers([
            ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
        ]);
        self::assertSame('minecraft:zombie', NetworkEntityIdentifierReader::decode($valid)[0]['identifier']);

        $missing = self::networkIdentifiers([
            ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
        ], omitField: 'rid');
        $unknown = self::networkIdentifiers([
            ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
        ], extraField: true);
        $duplicate = self::networkIdentifiers([
            ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
        ], duplicateField: 'id');
        $oversized = "\x0a\x00\x09" . self::networkString('idlist') . "\x0a" . self::signedVarInt(1_025);
        foreach ([$missing, $unknown, $duplicate, $oversized, substr($valid, 0, -1), $valid . "\x00"] as $bytes) {
            try {
                NetworkEntityIdentifierReader::decode($bytes);
                self::fail('Invalid entity-identifier network NBT was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCatalogRejectsDuplicateRuntimeIdsMissingEggsUnknownEggsAndPropertyOwners(): void
    {
        $emptyProperties = gzencode("\x0a\x00\x00\x00", 9);
        self::assertIsString($emptyProperties);
        $stoneItems = self::items(['minecraft:stone']);
        $cases = [
            [
                self::networkIdentifiers([
                    ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
                    ['id' => 'minecraft:skeleton', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
                ]),
                $emptyProperties,
                $stoneItems,
            ],
            [
                self::networkIdentifiers([
                    ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => true],
                ]),
                $emptyProperties,
                $stoneItems,
            ],
            [
                self::networkIdentifiers([
                    ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
                ]),
                $emptyProperties,
                self::items(['minecraft:ghost_spawn_egg']),
            ],
            [
                self::networkIdentifiers([
                    ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
                ]),
                self::entityProperties('minecraft:ghost'),
                $stoneItems,
            ],
        ];
        foreach ($cases as [$identifiers, $properties, $items]) {
            try {
                EntityTypeRegistry::fromAdmittedArtifacts($identifiers, $properties, $items);
                self::fail('Invalid entity catalog was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCatalogOrderingAndHashDoNotDependOnSourceListOrder(): void
    {
        $emptyProperties = gzencode("\x0a\x00\x00\x00", 9);
        self::assertIsString($emptyProperties);
        $entities = [
            ['id' => 'minecraft:zombie', 'rid' => 32, 'summonable' => true, 'hasSpawnEgg' => false],
            ['id' => 'minecraft:skeleton', 'rid' => 34, 'summonable' => true, 'hasSpawnEgg' => false],
        ];
        $forward = EntityTypeRegistry::fromAdmittedArtifacts(
            self::networkIdentifiers($entities),
            $emptyProperties,
            self::items(['minecraft:stone']),
        );
        $reverse = EntityTypeRegistry::fromAdmittedArtifacts(
            self::networkIdentifiers(array_reverse($entities)),
            $emptyProperties,
            self::items(['minecraft:stone']),
        );

        self::assertSame(array_keys($forward->definitions()), array_keys($reverse->definitions()));
        self::assertSame($forward->catalogSha256(), $reverse->catalogSha256());
    }

    /**
     * @param list<array{id: string, rid: int, summonable: bool, hasSpawnEgg: bool}> $entities
     */
    private static function networkIdentifiers(
        array $entities,
        ?string $omitField = null,
        bool $extraField = false,
        ?string $duplicateField = null,
    ): string {
        $bytes = "\x0a\x00\x09" . self::networkString('idlist') . "\x0a" . self::signedVarInt(count($entities));
        foreach ($entities as $entity) {
            $fields = [
                'bid' => "\x08" . self::networkString('bid') . self::networkString(''),
                'hasspawnegg' => "\x01" . self::networkString('hasspawnegg') . chr((int) $entity['hasSpawnEgg']),
                'id' => "\x08" . self::networkString('id') . self::networkString($entity['id']),
                'rid' => "\x03" . self::networkString('rid') . self::signedVarInt($entity['rid']),
                'summonable' => "\x01" . self::networkString('summonable') . chr((int) $entity['summonable']),
            ];
            foreach ($fields as $name => $field) {
                if ($name !== $omitField) {
                    $bytes .= $field;
                }
                if ($name === $duplicateField) {
                    $bytes .= $field;
                }
            }
            if ($extraField) {
                $bytes .= "\x01" . self::networkString('unexpected') . "\x00";
            }
            $bytes .= "\x00";
        }

        return $bytes . "\x00";
    }

    private static function entityProperties(string $entityIdentifier): string
    {
        $property = "\x03" . pack('n', strlen('type')) . 'type' . pack('N', EntityPropertyType::BOOLEAN->value)
            . "\x08" . pack('n', strlen('name')) . 'name'
            . pack('n', strlen('minecraft:test')) . 'minecraft:test' . "\x00";
        $entity = "\x09" . pack('n', strlen('properties')) . 'properties' . "\x0a" . pack('N', 1) . $property
            . "\x08" . pack('n', strlen('type')) . 'type'
            . pack('n', strlen($entityIdentifier)) . $entityIdentifier . "\x00";
        $root = "\x0a\x00\x00\x0a" . pack('n', strlen($entityIdentifier)) . $entityIdentifier . $entity . "\x00";
        $encoded = gzencode($root, 9);
        self::assertIsString($encoded);

        return $encoded;
    }

    /** @param list<string> $identifiers */
    private static function items(array $identifiers): ItemNetworkRegistry
    {
        $items = [];
        foreach ($identifiers as $runtimeId => $identifier) {
            $items[$identifier] = ['runtime_id' => $runtimeId + 1, 'component_based' => false, 'version' => 0];
        }

        return ItemNetworkRegistry::fromRequiredItems($items);
    }

    private static function networkString(string $value): string
    {
        return self::unsignedVarInt(strlen($value)) . $value;
    }

    private static function signedVarInt(int $value): string
    {
        return self::unsignedVarInt(($value << 1) ^ ($value >> 31));
    }

    private static function unsignedVarInt(int $value): string
    {
        $bytes = '';
        do {
            $byte = $value & 0x7f;
            $value >>= 7;
            $bytes .= chr($value === 0 ? $byte : $byte | 0x80);
        } while ($value !== 0);

        return $bytes;
    }
}
