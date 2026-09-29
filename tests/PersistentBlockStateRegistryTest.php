<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\KnownPersistentBlockState;
use Bedriox\Data\LittleEndianBlockStateNbtCodec;
use Bedriox\Data\OpaquePersistentBlockState;
use Bedriox\Data\PersistentBlockStateProperty;
use Bedriox\Data\PersistentBlockStateRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersistentBlockStateRegistryTest extends TestCase
{
    private const string AIR_HEX = '0a00000804006e616d650d006d696e6563726166743a61697203070076657273696f6e213c15010a06007374617465730000';
    private const string BEDROCK_HEX = '0a00000804006e616d6511006d696e6563726166743a626564726f636b03070076657273696f6e213c15010a0600737461746573010e00696e66696e696275726e5f626974000000';

    private static PersistentBlockStateRegistry $registry;
    private static LittleEndianBlockStateNbtCodec $codec;

    public static function setUpBeforeClass(): void
    {
        self::$registry = AdmittedRegistryDataSet::bundled()->persistentBlockStateRegistry();
        self::$codec = new LittleEndianBlockStateNbtCodec(self::$registry);
    }

    /** @param list<array{string, string, int|string}> $expectedProperties */
    #[DataProvider('knownGoldenProvider')]
    public function testKnownGoldenRootsPreserveTypesOrderVersionAndBytes(
        string $identifier,
        string $hex,
        array $expectedProperties,
    ): void {
        $bytes = self::hex($hex);
        $decoded = self::$codec->decode($bytes);
        self::assertSame(strlen($bytes), $decoded->nextOffset());
        self::assertInstanceOf(KnownPersistentBlockState::class, $decoded->state());
        $state = $decoded->state();
        self::assertSame($identifier, $state->identifier());
        self::assertSame(18_168_865, $state->storageVersion());
        self::assertSame($expectedProperties, self::propertyTuples($state->properties()));
        self::assertSame($bytes, $state->originalEncodedRoot());
        self::assertSame($bytes, self::$codec->encode($state));
    }

    /** @return iterable<string, array{string, string, list<array{string, string, int|string}>}> */
    public static function knownGoldenProvider(): iterable
    {
        yield 'air' => [
            'minecraft:air',
            self::AIR_HEX,
            [],
        ];
        yield 'bedrock byte' => [
            'minecraft:bedrock',
            self::BEDROCK_HEX,
            [['infiniburn_bit', 'Byte', 0]],
        ];
        yield 'blue candle mixed int and byte' => [
            'minecraft:blue_candle',
            '0a00000804006e616d6515006d696e6563726166743a626c75655f63616e646c6503070076657273696f6e213c15010a060073746174657303070063616e646c6573000000000103006c6974000000',
            [['candles', 'Int', 0], ['lit', 'Byte', 0]],
        ];
        yield 'dark oak wood string' => [
            'minecraft:dark_oak_wood',
            '0a00000804006e616d6517006d696e6563726166743a6461726b5f6f616b5f776f6f6403070076657273696f6e213c15010a0600737461746573080b0070696c6c61725f617869730100790000',
            [['pillar_axis', 'String', 'y']],
        ];
    }

    #[DataProvider('opaqueGoldenProvider')]
    public function testUnknownGoldenRootsRemainByteExactOpaque(string $hex): void
    {
        $bytes = self::hex($hex);
        $decoded = self::$codec->decode($bytes);
        self::assertInstanceOf(OpaquePersistentBlockState::class, $decoded->state());
        self::assertSame(strlen($bytes), $decoded->nextOffset());
        self::assertSame($bytes, $decoded->state()->encodedRoot());
        self::assertSame($bytes, self::$codec->encode($decoded->state()));
    }

    /** @return iterable<string, array{string}> */
    public static function opaqueGoldenProvider(): iterable
    {
        yield 'unknown identifier' => [
            '0a00000804006e616d6516006d696e6563726166743a6e6f745f61646d697474656403070076657273696f6e213c15010a06007374617465730000',
        ];
        yield 'unknown known-identifier combination' => [
            '0a00000804006e616d6511006d696e6563726166743a626564726f636b03070076657273696f6e213c15010a0600737461746573010e00696e66696e696275726e5f626974020000',
        ];
    }

    public function testAllAdmittedStatesRoundTripSemanticallyWithoutIds(): void
    {
        self::assertCount(22_079, self::$registry->states());
        $methods = get_class_methods(self::$registry);
        self::assertNotContains('networkRuntimeId', $methods);
        self::assertNotContains('internalId', $methods);
        foreach (self::$registry->states() as $state) {
            $encoded = self::$codec->encode($state);
            $decoded = self::$codec->decode($encoded)->state();
            self::assertInstanceOf(KnownPersistentBlockState::class, $decoded);
            self::assertSame($state->identifier(), $decoded->identifier());
            self::assertSame($state->storageVersion(), $decoded->storageVersion());
            self::assertSame($state->canonicalState()->canonicalKey(), $decoded->canonicalState()->canonicalKey());
            self::assertSame(self::propertyTuples($state->properties()), self::propertyTuples($decoded->properties()));
        }
    }

    public function testPersistentProjectionMatchesCanonicalPaletteAndTypedBaseline(): void
    {
        $networkStates = AdmittedRegistryDataSet::bundled()->blockStateRegistry()->states();
        $typeCounts = ['Byte' => 0, 'Int' => 0, 'String' => 0];
        $versions = [];
        foreach (self::$registry->states() as $index => $persistentState) {
            self::assertSame($networkStates[$index]->canonicalKey(), $persistentState->canonicalState()->canonicalKey());
            $versions[$persistentState->storageVersion()] = true;
            foreach ($persistentState->properties() as $property) {
                ++$typeCounts[$property->type()->name];
            }
        }
        self::assertSame(['Byte' => 30_218, 'Int' => 18_001, 'String' => 28_758], $typeCounts);
        self::assertSame([18_168_865], array_keys($versions));
    }

    public function testMalformedAdmittedPaletteInputFailsClosed(): void
    {
        foreach ([
            '',
            'not-gzip',
            substr(AdmittedRegistryDataSet::bundled()->artifact('block_palette'), 0, 128),
            str_repeat("\0", AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES + 1),
        ] as $bytes) {
            try {
                PersistentBlockStateRegistry::fromAdmittedPalette($bytes);
                self::fail('Malformed persistent block palette was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDecodeReportsNextOffsetForConcatenatedRoots(): void
    {
        $air = self::hex(self::AIR_HEX);
        $bedrock = self::hex(self::BEDROCK_HEX);
        $bytes = "prefix" . $air . $bedrock . "suffix";

        $first = self::$codec->decode($bytes, 6);
        $second = self::$codec->decode($bytes, $first->nextOffset());
        self::assertSame(6 + strlen($air), $first->nextOffset());
        self::assertSame(6 + strlen($air) + strlen($bedrock), $second->nextOffset());
        self::assertInstanceOf(KnownPersistentBlockState::class, $first->state());
        self::assertInstanceOf(KnownPersistentBlockState::class, $second->state());
    }

    public function testUnknownVersionAndTypeAreOpaque(): void
    {
        foreach ([
            KnownPersistentBlockState::from('minecraft:air', 18_168_864, []),
            KnownPersistentBlockState::from('minecraft:bedrock', 18_168_865, [PersistentBlockStateProperty::int('infiniburn_bit', 0)]),
        ] as $unknown) {
            self::assertInstanceOf(OpaquePersistentBlockState::class, self::$codec->decode(self::$codec->encode($unknown))->state());
        }
    }

    public function testKnownClassificationIgnoresRootAndPropertyOrderButPreservesBoth(): void
    {
        $reversed = KnownPersistentBlockState::from('minecraft:blue_candle', 18_168_865, [
            PersistentBlockStateProperty::byte('lit', 0),
            PersistentBlockStateProperty::int('candles', 0),
        ]);
        $encoded = self::$codec->encode($reversed);
        $decoded = self::$codec->decode($encoded)->state();
        self::assertInstanceOf(KnownPersistentBlockState::class, $decoded);
        self::assertSame([['lit', 'Byte', 0], ['candles', 'Int', 0]], self::propertyTuples($decoded->properties()));
        self::assertSame($encoded, self::$codec->encode($decoded));

        $rootReordered = self::root(
            "\x0a" . self::string('states') . "\x00"
            . "\x03" . self::string('version') . pack('V', 18_168_865)
            . "\x08" . self::string('name') . self::string('minecraft:air'),
        );
        $decoded = self::$codec->decode($rootReordered)->state();
        self::assertInstanceOf(KnownPersistentBlockState::class, $decoded);
        self::assertSame($rootReordered, self::$codec->encode($decoded));
    }

    /** @param callable(): mixed $operation */
    #[DataProvider('invalidInputProvider')]
    public function testMalformedAndBoundedInputsFailClosed(callable $operation): void
    {
        $this->expectException(RuntimeException::class);
        $operation();
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidInputProvider(): iterable
    {
        yield 'truncated' => [static fn() => self::$codec->decode("\x0a\x00")];
        yield 'named root' => [static fn() => self::$codec->decode("\x0a\x01\x00x\x00")];
        yield 'non-compound root' => [static fn() => self::$codec->decode("\x08\x00\x00")];
        yield 'missing fields' => [static fn() => self::$codec->decode("\x0a\x00\x00\x00")];
        yield 'unknown root field' => [static fn() => self::$codec->decode(self::root("\x08" . self::string('extra') . self::string('x')))];
        yield 'duplicate root field' => [static fn() => self::$codec->decode(self::root(
            "\x08" . self::string('name') . self::string('minecraft:air')
            . "\x08" . self::string('name') . self::string('minecraft:air'),
        ))];
        yield 'unsupported property type' => [static fn() => self::$codec->decode(self::syntheticRoot("\x02" . self::string('bad') . "\x00\x00"))];
        yield 'invalid utf8 property' => [static fn() => self::$codec->decode(self::syntheticRoot("\x08\x01\x00\xff\x00\x00"))];
        yield 'duplicate property' => [static fn() => self::$codec->decode(self::syntheticRoot(
            "\x01" . self::string('same') . "\x00" . "\x01" . self::string('same') . "\x01",
        ))];
        yield 'too many properties' => [static fn() => self::$codec->decode(self::syntheticRoot(
            implode('', array_map(
                static fn(int $index): string => "\x01" . self::string('p' . $index) . "\x00",
                range(0, 128),
            )),
        ))];
        yield 'trailing property payload truncation' => [static fn() => self::$codec->decode(self::syntheticRoot("\x03" . self::string('value') . "\x01"))];
        yield 'oversized root' => [static fn() => self::$codec->decode(self::syntheticRoot(
            implode('', array_map(
                static fn(int $index): string => "\x08" . self::string('p' . $index) . self::string(str_repeat('x', 1_024)),
                range(0, 63),
            )),
        ))];
    }

    #[DataProvider('invalidOffsetProvider')]
    public function testInvalidOffsetsFailClosed(int $offset): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::$codec->decode("\x0a\x00\x00\x00", $offset);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidOffsetProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'at end' => [4];
        yield 'past end' => [5];
    }

    public function testPropertyAndOpaqueFactoriesRejectInvalidValues(): void
    {
        foreach ([
            static fn() => PersistentBlockStateProperty::byte('value', 128),
            static fn() => PersistentBlockStateProperty::int('value', 2_147_483_648),
            static fn() => PersistentBlockStateProperty::string('value', "\xff"),
            static fn() => KnownPersistentBlockState::from('minecraft:air', 2_147_483_648, []),
            static fn() => OpaquePersistentBlockState::fromValidatedEncodedRoot('not nbt'),
            static fn() => OpaquePersistentBlockState::fromValidatedEncodedRoot("\x0a\x00\x00\x00"),
        ] as $operation) {
            try {
                $operation();
                self::fail('Invalid persistent state value was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDecodedKnownFactoryRejectsPreservedBytesForADifferentState(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KnownPersistentBlockState::fromDecoded(
            'minecraft:air',
            18_168_865,
            [],
            self::hex(self::BEDROCK_HEX),
        );
    }

    public function testDecodedKnownFactoryRejectsMismatchedVersionAndOrderedProperties(): void
    {
        foreach ([
            static fn() => KnownPersistentBlockState::fromDecoded(
                'minecraft:air',
                18_168_864,
                [],
                self::hex(self::AIR_HEX),
            ),
            static fn() => KnownPersistentBlockState::fromDecoded(
                'minecraft:bedrock',
                18_168_865,
                [PersistentBlockStateProperty::int('infiniburn_bit', 0)],
                self::hex(self::BEDROCK_HEX),
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('Mismatched preserved block-state bytes were accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @param list<\Bedriox\Data\PersistentBlockStateProperty> $properties
     * @return list<array{string, string, int|string}>
     */
    private static function propertyTuples(array $properties): array
    {
        return array_map(
            static fn(PersistentBlockStateProperty $property): array => [
                $property->name(), $property->type()->name, $property->value(),
            ],
            $properties,
        );
    }

    private static function syntheticRoot(string $propertyPayload): string
    {
        return self::root(
            "\x08" . self::string('name') . self::string('minecraft:test')
            . "\x03" . self::string('version') . pack('V', 18_168_865)
            . "\x0a" . self::string('states') . $propertyPayload . "\x00",
        );
    }

    private static function root(string $payload): string
    {
        return "\x0a\x00\x00" . $payload . "\x00";
    }

    private static function string(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }

    private static function hex(string $hex): string
    {
        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        return $bytes;
    }
}
