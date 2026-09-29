<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

/** Immutable current-version entity identity and source-proven spawn/property capabilities. */
final readonly class EntityTypeDefinition
{
    /** @var list<EntityPropertyDefinition> */
    private array $properties;

    /**
     * @param list<EntityPropertyDefinition> $properties
     */
    private function __construct(
        private string $identifier,
        private int $networkRuntimeId,
        private bool $summonable,
        private bool $spawnEggAdvertised,
        private ?string $spawnEggItemIdentifier,
        array $properties,
    ) {
        if (preg_match('/^minecraft:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
            throw new InvalidArgumentException('Entity identifier is not canonical minecraft identity.');
        }
        if ($networkRuntimeId < 0 || $networkRuntimeId > 0x7fffffff) {
            throw new InvalidArgumentException('Entity network runtime ID is outside its supported range.');
        }
        if ($spawnEggItemIdentifier !== null
            && (preg_match('/^minecraft:[a-z0-9_.-]+_spawn_egg$/D', $spawnEggItemIdentifier) !== 1
                || strlen($spawnEggItemIdentifier) > 256)) {
            throw new InvalidArgumentException('Entity spawn-egg item identifier is invalid.');
        }
        $byIdentifier = [];
        foreach ($properties as $property) {
            if (!$property instanceof EntityPropertyDefinition || isset($byIdentifier[$property->identifier()])) {
                throw new InvalidArgumentException('Entity properties contain an invalid or duplicate definition.');
            }
            $byIdentifier[$property->identifier()] = $property;
        }
        ksort($byIdentifier, SORT_STRING);
        $this->properties = array_values($byIdentifier);
    }

    /**
     * @param list<EntityPropertyDefinition> $properties
     * @internal Constructed by EntityTypeRegistry from hash-verified admitted artifacts.
     */
    public static function fromAdmittedEntity(
        string $identifier,
        int $networkRuntimeId,
        bool $summonable,
        bool $spawnEggAdvertised,
        ?string $spawnEggItemIdentifier,
        array $properties,
    ): self {
        return new self(
            $identifier,
            $networkRuntimeId,
            $summonable,
            $spawnEggAdvertised,
            $spawnEggItemIdentifier,
            $properties,
        );
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function networkRuntimeId(): int
    {
        return $this->networkRuntimeId;
    }

    public function summonable(): bool
    {
        return $this->summonable;
    }

    public function hasSpawnEgg(): bool
    {
        return $this->spawnEggItemIdentifier !== null;
    }

    /** Whether the current client actor registry advertises a spawn egg for this entity identity. */
    public function spawnEggAdvertised(): bool
    {
        return $this->spawnEggAdvertised;
    }

    public function spawnEggItemIdentifier(): ?string
    {
        return $this->spawnEggItemIdentifier;
    }

    /** @return list<EntityPropertyDefinition> */
    public function properties(): array
    {
        return $this->properties;
    }
}
