<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Immutable source-neutral entity catalog derived from the active admitted current-version artifacts. */
final readonly class EntityTypeRegistry
{
    /** Current item identities whose corresponding actor identity is not a direct suffix match. */
    private const array SPAWN_EGG_ENTITY_ALIASES = [
        'minecraft:evoker_spawn_egg' => 'minecraft:evocation_illager',
        'minecraft:tropical_fish_spawn_egg' => 'minecraft:tropicalfish',
        'minecraft:villager_spawn_egg' => 'minecraft:villager_v2',
        'minecraft:zombie_villager_spawn_egg' => 'minecraft:zombie_villager_v2',
    ];

    /** @var array<string, EntityTypeDefinition> */
    private array $definitionsByIdentifier;

    /** @var array<int, EntityTypeDefinition> */
    private array $definitionsByNetworkRuntimeId;

    /** @var array<string, EntityTypeDefinition> spawn-egg item identifier => entity */
    private array $entitiesBySpawnEgg;

    private string $catalogSha256;

    private function __construct(string $identifiersNbt, string $propertiesNbt, ItemNetworkRegistry $items)
    {
        $records = NetworkEntityIdentifierReader::decode($identifiersNbt);
        $properties = BigEndianNbtRegistry::entityProperties($propertiesNbt);
        $spawnEggItems = [];
        foreach ($items->definitions() as $item) {
            $itemIdentifier = $item->identifier();
            if (!str_starts_with($itemIdentifier, 'minecraft:') || !str_ends_with($itemIdentifier, '_spawn_egg')) {
                continue;
            }
            $entityName = substr($itemIdentifier, strlen('minecraft:'), -strlen('_spawn_egg'));
            $entityIdentifier = self::SPAWN_EGG_ENTITY_ALIASES[$itemIdentifier] ?? 'minecraft:' . $entityName;
            if ($entityName === '' || isset($spawnEggItems[$entityIdentifier])) {
                throw new RuntimeException('Admitted spawn-egg items do not form a unique entity mapping.');
            }
            $spawnEggItems[$entityIdentifier] = $itemIdentifier;
        }

        $byIdentifier = [];
        $byRuntimeId = [];
        $bySpawnEgg = [];
        foreach ($records as $record) {
            $identifier = $record['identifier'];
            $runtimeId = $record['network_runtime_id'];
            if (isset($byIdentifier[$identifier]) || isset($byRuntimeId[$runtimeId])) {
                throw new RuntimeException('Entity catalog contains a duplicate identifier or network runtime ID.');
            }
            $spawnEgg = $spawnEggItems[$identifier] ?? null;
            if ($record['has_spawn_egg'] && $spawnEgg === null) {
                throw new RuntimeException('Entity advertises a spawn egg absent from the admitted item registry.');
            }
            try {
                $definition = EntityTypeDefinition::fromAdmittedEntity(
                    $identifier,
                    $runtimeId,
                    $record['summonable'],
                    $record['has_spawn_egg'],
                    $spawnEgg,
                    $properties[$identifier] ?? [],
                );
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException('Entity catalog contains an invalid definition.', previous: $error);
            }
            $byIdentifier[$identifier] = $definition;
            $byRuntimeId[$runtimeId] = $definition;
            if ($spawnEgg !== null) {
                $bySpawnEgg[$spawnEgg] = $definition;
                unset($spawnEggItems[$identifier]);
            }
        }
        if ($spawnEggItems !== []) {
            throw new RuntimeException('Admitted item registry contains a spawn egg for an unknown entity.');
        }
        foreach ($properties as $identifier => $_definitions) {
            if (!isset($byIdentifier[$identifier])) {
                throw new RuntimeException('Entity-property artifact contains an unknown entity identifier.');
            }
        }
        ksort($byIdentifier, SORT_STRING);
        ksort($byRuntimeId, SORT_NUMERIC);
        ksort($bySpawnEgg, SORT_STRING);
        $this->definitionsByIdentifier = $byIdentifier;
        $this->definitionsByNetworkRuntimeId = $byRuntimeId;
        $this->entitiesBySpawnEgg = $bySpawnEgg;
        $this->catalogSha256 = self::hash($byIdentifier);
    }

    /** @internal Construct only from hash-verified active admitted artifacts. */
    public static function fromAdmittedArtifacts(
        string $identifiersNbt,
        string $propertiesNbt,
        ItemNetworkRegistry $items,
    ): self {
        return new self($identifiersNbt, $propertiesNbt, $items);
    }

    /** @return array<string, EntityTypeDefinition> sorted by canonical identifier */
    public function definitions(): array
    {
        return $this->definitionsByIdentifier;
    }

    public function definitionForIdentifier(string $identifier): EntityTypeDefinition
    {
        return $this->definitionsByIdentifier[$identifier]
            ?? throw new InvalidArgumentException('Identifier is not present in the admitted entity catalog.');
    }

    public function definitionForNetworkRuntimeId(int $networkRuntimeId): EntityTypeDefinition
    {
        return $this->definitionsByNetworkRuntimeId[$networkRuntimeId]
            ?? throw new InvalidArgumentException('Network runtime ID is not present in the admitted entity catalog.');
    }

    public function entityForSpawnEgg(string $itemIdentifier): EntityTypeDefinition
    {
        return $this->entitiesBySpawnEgg[$itemIdentifier]
            ?? throw new InvalidArgumentException('Item is not an admitted entity spawn egg.');
    }

    /** @return array<string, EntityTypeDefinition> sorted by spawn-egg item identifier */
    public function spawnEggMappings(): array
    {
        return $this->entitiesBySpawnEgg;
    }

    /** Stable semantic hash independent of entity-list order. */
    public function catalogSha256(): string
    {
        return $this->catalogSha256;
    }

    /** @param array<string, EntityTypeDefinition> $definitions */
    private static function hash(array $definitions): string
    {
        $normalized = [];
        foreach ($definitions as $definition) {
            $properties = [];
            foreach ($definition->properties() as $property) {
                $properties[] = [
                    'enum_values' => $property->enumValues(),
                    'identifier' => $property->identifier(),
                    'maximum' => $property->maximum(),
                    'minimum' => $property->minimum(),
                    'type' => $property->type()->value,
                ];
            }
            $normalized[] = [
                'identifier' => $definition->identifier(),
                'network_runtime_id' => $definition->networkRuntimeId(),
                'properties' => $properties,
                'spawn_egg_advertised' => $definition->spawnEggAdvertised(),
                'spawn_egg_item' => $definition->spawnEggItemIdentifier(),
                'summonable' => $definition->summonable(),
            ];
        }
        try {
            $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $error) {
            throw new RuntimeException('Unable to hash the admitted entity catalog.', previous: $error);
        }

        return hash('sha256', $json);
    }
}
