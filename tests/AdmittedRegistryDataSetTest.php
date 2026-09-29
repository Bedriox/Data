<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\BigEndianNbtRegistry;
use Bedriox\Data\BedrockVersion;
use Bedriox\Data\CanonicalBlockStateIndex;
use Bedriox\Data\LittleEndianNbtValidator;

final class AdmittedRegistryDataSetTest extends TestCase
{
    public function testEveryBundledArtifactHasItsAdmittedHashAndSemantics(): void
    {
        $data = AdmittedRegistryDataSet::bundled();
        self::assertCount(17, AdmittedRegistryDataSet::artifactNames());
        foreach (['block_palette', 'runtime_item_states', 'item_components', 'biome_definitions', 'entity_identifiers', 'data_driven_blocks', 'creative_inventory', 'block_item_mappings', 'block_properties', 'voxel_shapes'] as $required) {
            self::assertContains($required, AdmittedRegistryDataSet::artifactNames());
        }
        foreach (AdmittedRegistryDataSet::artifactNames() as $name) {
            self::assertNotSame('', $data->artifact($name));
        }
        self::assertSame([
            'air' => -604_749_536,
            'bedrock' => -173_245_189,
            'dirt' => -2_108_756_090,
            'grass_block' => -567_203_660,
        ], $data->fixedFlatRuntimeIds());
        self::assertCount(2_076, $data->requiredItems());
        self::assertCount(89, $data->biomeDefinitions());
        self::assertSame(1, $data->plainsBiomeRuntimeId());
        self::assertNotSame('', $data->entityIdentifiersNetworkNbt());
        self::assertCount(98, $data->dataDrivenBlockProperties());
    }

    public function testAdmissionManifestPinsCurrentReviewedSourceAndEveryArtifact(): void
    {
        $path = dirname(__DIR__) . '/' . BedrockVersion::ADMISSION_MANIFEST;
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $manifest = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame(2_193, $manifest['protocolVersion'] ?? null);
        self::assertSame('1.26.52', $manifest['qualifiedClientVersion'] ?? null);
        $source = $manifest['source'] ?? null;
        $generator = $manifest['generator'] ?? null;
        $artifacts = $manifest['artifacts'] ?? null;
        self::assertIsArray($source);
        self::assertIsArray($generator);
        self::assertIsArray($artifacts);
        self::assertSame('6a47607b2c1e65ec38032c5b2e473e0da9a5e80c', $source['commit'] ?? null);
        self::assertSame('LicenseRef-Minecraft-EULA-Interoperability-Review', $source['license'] ?? null);
        $generatorRevision = $generator['revision'] ?? null;
        self::assertIsString($generatorRevision);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/D', $generatorRevision);
        self::assertCount(17, $artifacts);
    }

    public function testBiomeRuntimeRegistryUsesCanonicalVanillaIds(): void
    {
        $ids = AdmittedRegistryDataSet::bundled()->biomeRuntimeIds();

        self::assertCount(89, $ids);
        self::assertCount(89, array_unique($ids, SORT_REGULAR));
        self::assertSame(0, $ids['minecraft:ocean']);
        self::assertSame(1, $ids['minecraft:plains']);
        self::assertSame(24, $ids['minecraft:deep_ocean']);
        self::assertSame(129, $ids['minecraft:sunflower_plains']);
        self::assertSame(194, $ids['minecraft:sulfur_caves']);
        self::assertSame(195, $ids['minecraft:dappled_forest']);
    }

    public function testUnknownArtifactFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdmittedRegistryDataSet::bundled()->artifact('../unknown');
    }

    public function testMissingOrChangedArtifactFailsBeforeUse(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedriox-data-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        try {
            file_put_contents($directory . DIRECTORY_SEPARATOR . 'block_palette.nbt', str_repeat('x', 273_939));
            $this->expectException(RuntimeException::class);
            AdmittedRegistryDataSet::fromDirectory($directory)->artifact('block_palette');
        } finally {
            @unlink($directory . DIRECTORY_SEPARATOR . 'block_palette.nbt');
            @rmdir($directory);
        }
    }

    public function testSymlinkedArtifactIsRejectedWhenPlatformSupportsIt(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedriox-data-link-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $link = $directory . DIRECTORY_SEPARATOR . 'block_palette.nbt';
        $target = dirname(__DIR__) . '/artifacts/bedrock-1.26.52-protocol-2193/block_palette.nbt';
        if (!@symlink($target, $link)) { @rmdir($directory); self::markTestSkipped('Symlink creation is unavailable on this platform.'); }
        try {
            $this->expectException(RuntimeException::class);
            AdmittedRegistryDataSet::fromDirectory($directory)->artifact('block_palette');
        } finally { @unlink($link); @rmdir($directory); }
    }

    public function testNetworkNbtValidatorRejectsMalformedTruncatedOversizedAndTrailingInput(): void
    {
        CanonicalBlockStateIndex::validateRootCompound("\x0a\x00\x00", 3);
        self::addToAssertionCount(1);
        foreach (["\x0a\x00", "\x09\x00\x00", "\x0a\x00\x00\x00", str_repeat("\0", AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES + 1)] as $bytes) {
            try {
                CanonicalBlockStateIndex::validateRootCompound($bytes, AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES);
                self::fail('Malformed network-NBT root was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLittleEndianNbtValidatorRejectsMalformedTruncatedAndTrailingInput(): void
    {
        LittleEndianNbtValidator::rootCompound("\x0a\x00\x00\x00", 64);
        self::addToAssertionCount(1);
        foreach (["\x0a\x00", "\x09\x00\x00", "\x0a\x00\x00\x00\x00", hex2bin('0a00000901006c0d0000000000') ?: ''] as $bytes) {
            try { LittleEndianNbtValidator::rootCompound($bytes, 64); self::fail('Malformed little-endian NBT was accepted.'); }
            catch (RuntimeException) { self::addToAssertionCount(1); }
        }
    }

    public function testCompressedBigEndianNbtRejectsMalformedAndTruncatedInput(): void
    {
        foreach (['not-gzip', substr(self::gzip("\x0a\x00\x00\x00"), 0, -2), self::gzip("\x09\x00\x00")] as $bytes) {
            try {
                BigEndianNbtRegistry::fixedFlatRuntimeIds($bytes);
                self::fail('Malformed compressed palette NBT was accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(RuntimeException::class);
        BigEndianNbtRegistry::itemComponents(self::gzip("\x0a\x00\x00\x08\x00\x01x\x00\x00"));
    }

    public function testDataDrivenBlockPropertiesAreBoundedNamedCompounds(): void
    {
        $properties = AdmittedRegistryDataSet::bundled()->dataDrivenBlockProperties();
        self::assertCount(98, $properties);
        self::assertArrayHasKey('minecraft:light_gray_concrete_stairs', $properties);
        foreach ($properties as $identifier => $nbt) {
            self::assertMatchesRegularExpression('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier);
            LittleEndianNbtValidator::rootCompound($nbt, 262_144);
        }
    }

    public function testDataDrivenBlockPropertiesRejectMalformedTruncatedAndOversizedInput(): void
    {
        $admitted = AdmittedRegistryDataSet::bundled()->artifact('data_driven_blocks');
        foreach ([
            'not-gzip',
            substr($admitted, 0, -1),
            self::gzip("\x09\x00\x00"),
            str_repeat("\0", AdmittedRegistryDataSet::MAX_ARTIFACT_BYTES + 1),
        ] as $bytes) {
            try {
                BigEndianNbtRegistry::dataDrivenBlockProperties($bytes);
                self::fail('Malformed data-driven block properties were accepted.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testBigEndianConversionBoundsEncodedTagTypeBytes(): void
    {
        $valid = "\x0a\x00\x00\x0a\x00\x01x\x09\x00\x01l\x0c\x00\x00\x00\x00\x00\x00";
        self::assertSame([
            'x' => "\x0a\x01\x00x\x09\x01\x00l\x0c\x00\x00\x00\x00\x00",
        ], BigEndianNbtRegistry::dataDrivenBlockProperties(self::gzip($valid)));

        $unknownCompoundType = "\x0a\x00\x00\x0a\x00\x01x\xff\x00\x01y";
        $unknownEmptyListType = "\x0a\x00\x00\x0a\x00\x01x\x09\x00\x01l\xff\x00\x00\x00\x00\x00\x00";
        foreach ([$unknownCompoundType, $unknownEmptyListType] as $bytes) {
            try {
                BigEndianNbtRegistry::dataDrivenBlockProperties(self::gzip($bytes));
                self::fail('An out-of-domain NBT tag type was encoded.');
            } catch (RuntimeException $exception) {
                self::assertSame('NBT contains an unknown tag type.', $exception->getMessage());
            }
        }
    }

    private static function gzip(string $bytes): string
    {
        $encoded = gzencode($bytes, 9);
        if (!is_string($encoded)) {
            self::fail('Unable to create a compressed NBT test fixture.');
        }
        return $encoded;
    }
}
