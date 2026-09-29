<?php

declare(strict_types=1);

namespace Bedriox\Data;

use InvalidArgumentException;
use RuntimeException;

/** Immutable ordered mapping between canonical block states and Bedrock network runtime IDs. */
final readonly class NetworkBlockStateRegistry
{
    /** @var list<CanonicalBlockState> */
    private array $states;

    /** @var array<string, int> */
    private array $runtimeIdsByKey;

    /** @var array<int, CanonicalBlockState> */
    private array $statesByRuntimeId;

    /** @param list<array{state: CanonicalBlockState, network_id: int}> $entries */
    private function __construct(array $entries)
    {
        if ($entries === [] || count($entries) > 100_000) {
            throw new RuntimeException('Network block-state palette is empty or oversized.');
        }
        $states = [];
        $runtimeIdsByKey = [];
        $statesByRuntimeId = [];
        foreach ($entries as $entry) {
            $state = $entry['state'];
            $networkRuntimeId = $entry['network_id'];
            $key = $state->canonicalKey();
            if (isset($runtimeIdsByKey[$key]) || isset($statesByRuntimeId[$networkRuntimeId])) {
                throw new RuntimeException('Network block-state palette contains a duplicate canonical state or network ID.');
            }
            $states[] = $state;
            $runtimeIdsByKey[$key] = $networkRuntimeId;
            $statesByRuntimeId[$networkRuntimeId] = $state;
        }
        $this->states = $states;
        $this->runtimeIdsByKey = $runtimeIdsByKey;
        $this->statesByRuntimeId = $statesByRuntimeId;
    }

    /** @internal Construct only from a hash-verified admitted ordered palette. */
    public static function fromAdmittedPalette(string $compressedNbt): self
    {
        return new self(BigEndianNbtRegistry::networkBlockPalette($compressedNbt));
    }

    /** @return list<CanonicalBlockState> */
    public function states(): array
    {
        return $this->states;
    }

    public function networkRuntimeId(CanonicalBlockState $state): int
    {
        return $this->runtimeIdsByKey[$state->canonicalKey()]
            ?? throw new InvalidArgumentException('Canonical block state is not present in the admitted network palette.');
    }

    public function stateForNetworkRuntimeId(int $networkRuntimeId): CanonicalBlockState
    {
        $networkRuntimeId = self::normalizeNetworkRuntimeId($networkRuntimeId);
        return $this->statesByRuntimeId[$networkRuntimeId]
            ?? throw new InvalidArgumentException('Network runtime ID is outside the admitted block-state palette.');
    }

    private static function normalizeNetworkRuntimeId(int $networkRuntimeId): int
    {
        if ($networkRuntimeId >= -0x8000_0000 && $networkRuntimeId <= 0x7fff_ffff) {
            return $networkRuntimeId;
        }
        if ($networkRuntimeId >= 0x8000_0000 && $networkRuntimeId <= 0xffff_ffff) {
            return $networkRuntimeId - 0x1_0000_0000;
        }
        throw new InvalidArgumentException('Network runtime ID must fit signed or unsigned 32-bit representation.');
    }
}
