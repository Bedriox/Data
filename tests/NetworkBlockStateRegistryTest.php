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
        $state = CanonicalBlockState::from('minecraft:bedrock', ['infiniburn_bit' => 0]);
        $properties = array_reverse($state->properties(), true);

        self::assertSame(-173_245_189, $registry->networkRuntimeId(CanonicalBlockState::from($state->identifier(), $properties)));
        self::assertSame($state->canonicalKey(), CanonicalBlockState::from($state->identifier(), $properties)->canonicalKey());
    }

    public function testExplicitSignedHashesAreUniqueAndAcceptUnsignedLookupProjection(): void
    {
        $registry = AdmittedRegistryDataSet::bundled()->blockStateRegistry();
        $runtimeIds = array_map($registry->networkRuntimeId(...), $registry->states());

        self::assertCount(22_079, $runtimeIds);
        self::assertCount(22_079, array_unique($runtimeIds, SORT_REGULAR));
        self::assertSame(-604_749_536, $registry->networkRuntimeId(CanonicalBlockState::from('minecraft:air')));
        self::assertSame('minecraft:air', $registry->stateForNetworkRuntimeId(3_690_217_760)->identifier());
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
            static fn() => $registry->stateForNetworkRuntimeId(0),
            static fn() => $registry->stateForNetworkRuntimeId(0x1_0000_0000),
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

    public function testMismatchedExplicitNetworkHashFailsClosed(): void
    {
        $decoded = gzdecode(AdmittedRegistryDataSet::bundled()->artifact('block_palette'));
        self::assertIsString($decoded);
        $offset = strpos($decoded, pack('N', 973_836_165));
        self::assertIsInt($offset);
        $decoded = substr_replace($decoded, pack('N', 973_836_166), $offset, 4);
        $corrupted = gzencode($decoded, 9);
        self::assertIsString($corrupted);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match canonical state hash');
        \Bedriox\Data\NetworkBlockStateRegistry::fromAdmittedPalette($corrupted);
    }
}
