<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** Immutable bidirectional lookup over the active admitted Bedrock item registry. */
final readonly class ItemNetworkRegistry
{
    /** @var array<string, ItemNetworkDefinition> */
    private array $definitionsByIdentifier;

    /** @var array<int, ItemNetworkDefinition> */
    private array $definitionsByNetworkRuntimeId;

    /**
     * @param array<array-key, mixed> $requiredItems
     * @internal Construct only from AdmittedRegistryDataSet::requiredItems().
     */
    public static function fromRequiredItems(array $requiredItems): self
    {
        return new self($requiredItems);
    }

    /** @param array<array-key, mixed> $requiredItems */
    private function __construct(array $requiredItems)
    {
        if ($requiredItems === [] || count($requiredItems) > 5_000) {
            throw new RuntimeException('Item network registry is empty or oversized.');
        }
        $byIdentifier = [];
        $byRuntimeId = [];
        foreach ($requiredItems as $identifier => $item) {
            $hasComponentNbt = is_array($item) && array_key_exists('component_nbt', $item);
            if (!is_string($identifier) || !is_array($item)
                || !is_int($item['runtime_id'] ?? null) || !is_bool($item['component_based'] ?? null)
                || !is_int($item['version'] ?? null) || count($item) !== ($hasComponentNbt ? 4 : 3)
                || ($hasComponentNbt && !is_string($item['component_nbt']))) {
                throw new RuntimeException('Item network registry contains an invalid definition shape.');
            }
            $componentNbt = null;
            if ($hasComponentNbt) {
                $componentNbt = base64_decode($item['component_nbt'], true);
                if (!is_string($componentNbt)) {
                    throw new RuntimeException('Item network registry contains invalid component NBT encoding.');
                }
            }
            try {
                $definition = ItemNetworkDefinition::fromAdmittedItem(
                    $identifier,
                    $item['runtime_id'],
                    $item['component_based'],
                    $item['version'],
                    $componentNbt,
                );
            } catch (InvalidArgumentException|RuntimeException $error) {
                throw new RuntimeException('Item network registry contains an invalid definition.', previous: $error);
            }
            $runtimeId = $definition->networkRuntimeId();
            if (isset($byIdentifier[$identifier]) || isset($byRuntimeId[$runtimeId])) {
                throw new RuntimeException('Item network registry contains a duplicate identifier or runtime ID.');
            }
            $byIdentifier[$identifier] = $definition;
            $byRuntimeId[$runtimeId] = $definition;
        }
        $this->definitionsByIdentifier = $byIdentifier;
        $this->definitionsByNetworkRuntimeId = $byRuntimeId;
    }

    /** @return array<string, ItemNetworkDefinition> */
    public function definitions(): array
    {
        return $this->definitionsByIdentifier;
    }

    public function definitionForIdentifier(string $identifier): ItemNetworkDefinition
    {
        return $this->definitionsByIdentifier[$identifier]
            ?? throw new InvalidArgumentException('Identifier is not present in the admitted item network registry.');
    }

    public function definitionForNetworkRuntimeId(int $networkRuntimeId): ItemNetworkDefinition
    {
        return $this->definitionsByNetworkRuntimeId[$networkRuntimeId]
            ?? throw new InvalidArgumentException('Network runtime ID is not present in the admitted item network registry.');
    }
}
