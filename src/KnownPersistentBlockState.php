<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;

final readonly class KnownPersistentBlockState implements PersistentBlockState
{
    /** @var list<PersistentBlockStateProperty> */
    private array $properties;
    private CanonicalBlockState $canonicalState;

    /** @param list<PersistentBlockStateProperty> $properties */
    private function __construct(
        private string $identifier,
        private int $storageVersion,
        array $properties,
        private ?string $originalEncodedRoot,
    ) {
        if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || strlen($identifier) > 256) {
            throw new InvalidArgumentException('Persistent block-state identifier is invalid.');
        }
        if (count($properties) > 128) {
            throw new InvalidArgumentException('Persistent block state has too many properties.');
        }
        if ($storageVersion < -2_147_483_648 || $storageVersion > 2_147_483_647) {
            throw new InvalidArgumentException('Persistent block-state storage version is outside its signed range.');
        }
        $names = [];
        $canonicalProperties = [];
        foreach ($properties as $property) {
            $name = $property->name();
            if (isset($names[$name])) {
                throw new InvalidArgumentException('Persistent block state contains a duplicate property.');
            }
            $names[$name] = true;
            $canonicalProperties[$name] = $property->value();
        }
        $this->properties = $properties;
        $this->canonicalState = CanonicalBlockState::from($identifier, $canonicalProperties);
    }

    /**
     * @param list<PersistentBlockStateProperty> $properties
     * @internal Constructs an admitted source value without preserved encoded bytes.
     */
    public static function from(
        string $identifier,
        int $storageVersion,
        array $properties,
    ): self {
        return new self($identifier, $storageVersion, $properties, null);
    }

    /**
     * @param list<PersistentBlockStateProperty> $properties
     * @internal Constructs a decoded admitted value only when the preserved root exactly matches every supplied field.
     */
    public static function fromDecoded(
        string $identifier,
        int $storageVersion,
        array $properties,
        string $originalEncodedRoot,
    ): self {
        $state = new self($identifier, $storageVersion, $properties, $originalEncodedRoot);
        try {
            $root = LittleEndianBlockStateNbtRootReader::decode($originalEncodedRoot);
        } catch (InvalidArgumentException|\RuntimeException $error) {
            throw new InvalidArgumentException('Original persistent block-state root is malformed.', previous: $error);
        }
        if ($root->nextOffset() !== strlen($originalEncodedRoot)
            || $root->identifier() !== $state->identifier
            || $root->storageVersion() !== $state->storageVersion
            || !self::sameProperties($root->properties(), $state->properties)) {
            throw new InvalidArgumentException('Original persistent block-state root does not match the supplied state.');
        }
        return $state;
    }

    /**
     * @param list<PersistentBlockStateProperty> $left
     * @param list<PersistentBlockStateProperty> $right
     */
    private static function sameProperties(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $property) {
            $other = $right[$index];
            if ($property->name() !== $other->name()
                || $property->type() !== $other->type()
                || $property->value() !== $other->value()) {
                return false;
            }
        }
        return true;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function storageVersion(): int
    {
        return $this->storageVersion;
    }

    /** @return list<PersistentBlockStateProperty> */
    public function properties(): array
    {
        return $this->properties;
    }

    public function canonicalState(): CanonicalBlockState
    {
        return $this->canonicalState;
    }

    public function originalEncodedRoot(): ?string
    {
        return $this->originalEncodedRoot;
    }
}
