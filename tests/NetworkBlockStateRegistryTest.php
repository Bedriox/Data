<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\CanonicalBlockState;

final class NetworkBlockStateRegistryTest extends TestCase
{
    public function testEquivalentCanonicalStateResolvesIndependentOfPropertyOrder(): void
    {
        $registry = AdmittedRegistryDataSet::bundled()->blockStateRegistry();
        $state = $registry->stateForNetworkRuntimeId(0);
        $properties = array_reverse($state->properties(), true);

        self::assertSame(0, $registry->networkRuntimeId(CanonicalBlockState::from($state->identifier(), $properties)));
        self::assertSame($state->canonicalKey(), CanonicalBlockState::from($state->identifier(), $properties)->canonicalKey());
    }

    /** @param array<array-key, mixed> $properties */
    #[DataProvider('invalidCanonicalStateProvider')]
    public function testCanonicalStateRejectsMalformedOrOversizedInput(string $identifier, array $properties): void
    {
        $this->expectException(InvalidArgumentException::class);
        CanonicalBlockState::from($identifier, $properties);
    }

    /** @return iterable<string, array{string, array<mixed, mixed>}> */
    public static function invalidCanonicalStateProvider(): iterable
    {
        yield 'missing namespace' => ['grass_block', []];
        yield 'uppercase' => ['minecraft:Grass', []];
        yield 'oversized identifier' => ['minecraft:' . str_repeat('a', 300), []];
        yield 'invalid property name' => ['minecraft:stone', ['bad key' => 1]];
        yield 'nested property' => ['minecraft:stone', ['value' => []]];
        yield 'invalid utf8' => ['minecraft:stone', ['value' => "\xff"]];
        yield 'too many properties' => ['minecraft:stone', array_fill(0, 129, 1)];
    }

    public function testUnknownStateAndRuntimeIdFailClosed(): void
    {
        $registry = AdmittedRegistryDataSet::bundled()->blockStateRegistry();
        foreach ([
            static fn() => $registry->networkRuntimeId(CanonicalBlockState::from('bedriox:not_admitted')),
            static fn() => $registry->stateForNetworkRuntimeId(-1),
            static fn() => $registry->stateForNetworkRuntimeId(count($registry->states())),
        ] as $operation) {
            try {
                $operation();
                self::fail('Unknown block-state lookup was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTruncatedMalformedAndOversizedPaletteInputFailClosed(): void
    {
        foreach ([
            '',
            'not-gzip',
            substr(AdmittedRegistryDataSet::bundled()->artifact('block_palette'), 0, 128),
            str_repeat("\0", AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES + 1),
        ] as $bytes) {
            try {
                \Bedriox\Data\NetworkBlockStateRegistry::fromAdmittedPalette($bytes);
                self::fail('Malformed block palette was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
