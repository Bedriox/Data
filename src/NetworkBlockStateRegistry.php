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

    /** @param list<CanonicalBlockState> $states */
    private function __construct(array $states)
    {
        if ($states === [] || count($states) > 100_000) {
            throw new RuntimeException('Network block-state palette is empty or oversized.');
        }
        $runtimeIdsByKey = [];
        foreach ($states as $networkRuntimeId => $state) {
            $key = $state->canonicalKey();
            if (isset($runtimeIdsByKey[$key])) {
                throw new RuntimeException('Network block-state palette contains a duplicate canonical state.');
            }
            $runtimeIdsByKey[$key] = $networkRuntimeId;
        }
        $this->states = $states;
        $this->runtimeIdsByKey = $runtimeIdsByKey;
    }

    /** @internal Construct only from a hash-verified admitted ordered palette. */
    public static function fromAdmittedPalette(string $compressedNbt): self
    {
        return new self(BigEndianNbtRegistry::blockPalette($compressedNbt));
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
        return $this->states[$networkRuntimeId]
            ?? throw new InvalidArgumentException('Network runtime ID is outside the admitted block-state palette.');
    }
}
