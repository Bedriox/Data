<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Separate persistent-state authority; it never exposes network or process-local IDs. */
final readonly class PersistentBlockStateRegistry
{
    /** @var list<KnownPersistentBlockState> */
    private array $states;

    /** @var array<string, KnownPersistentBlockState> */
    private array $statesByPersistentKey;

    /** @var array<string, KnownPersistentBlockState> */
    private array $statesByCanonicalKey;

    /** @param list<KnownPersistentBlockState> $states */
    private function __construct(array $states)
    {
        if ($states === [] || count($states) > 100_000) {
            throw new RuntimeException('Persistent block-state palette is empty or oversized.');
        }
        $persistent = [];
        $canonical = [];
        foreach ($states as $state) {
            $persistentKey = self::persistentKey($state->identifier(), $state->storageVersion(), $state->properties());
            $canonicalKey = $state->canonicalState()->canonicalKey();
            if (isset($persistent[$persistentKey]) || isset($canonical[$canonicalKey])) {
                throw new RuntimeException('Persistent block-state palette contains a duplicate state.');
            }
            $persistent[$persistentKey] = $state;
            $canonical[$canonicalKey] = $state;
        }
        $this->states = $states;
        $this->statesByPersistentKey = $persistent;
        $this->statesByCanonicalKey = $canonical;
    }

    /** @internal Construct only from a hash-verified admitted ordered palette. */
    public static function fromAdmittedPalette(string $compressedNbt): self
    {
        return new self(BigEndianNbtRegistry::persistentBlockPalette($compressedNbt));
    }

    /** @return list<KnownPersistentBlockState> */
    public function states(): array
    {
        return $this->states;
    }

    public function knownState(CanonicalBlockState $state): KnownPersistentBlockState
    {
        return $this->statesByCanonicalKey[$state->canonicalKey()]
            ?? throw new InvalidArgumentException('Canonical block state is not present in the admitted persistent palette.');
    }

    /**
     * @param list<PersistentBlockStateProperty> $properties
     * @internal Classification is performed by LittleEndianBlockStateNbtCodec after strict decoding.
     */
    public function classify(
        string $identifier,
        int $storageVersion,
        array $properties,
        string $encodedRoot,
    ): PersistentBlockState {
        $key = self::persistentKey($identifier, $storageVersion, $properties);
        if (isset($this->statesByPersistentKey[$key])) {
            return KnownPersistentBlockState::fromDecoded($identifier, $storageVersion, $properties, $encodedRoot);
        }
        return OpaquePersistentBlockState::fromValidatedEncodedRoot($encodedRoot);
    }

    /** @param list<PersistentBlockStateProperty> $properties */
    private static function persistentKey(string $identifier, int $storageVersion, array $properties): string
    {
        $typed = [];
        foreach ($properties as $property) {
            if (isset($typed[$property->name()])) {
                throw new InvalidArgumentException('Persistent block state contains a duplicate property.');
            }
            $typed[$property->name()] = [$property->type()->value, $property->value()];
        }
        ksort($typed, SORT_STRING);
        try {
            return json_encode([$identifier, $storageVersion, $typed], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Persistent block state cannot be keyed.', previous: $error);
        }
    }
}
