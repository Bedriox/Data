<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Bedriox\Data\ReleaseBundleAdmissionValidator;

final class ReleaseBundleAdmissionValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->directories) as $directory) {
            self::removeDirectory($directory);
        }
    }

    public function testApprovedBundleValidatesAndAppliesWithoutReplacingAnything(): void
    {
        $bundle = $this->bundle();
        $approved = (new ReleaseBundleAdmissionValidator())->validate($bundle);
        self::assertSame('1.26.60', $approved->gameVersion());
        self::assertSame(2_200, $approved->protocolVersion());
        self::assertCount(11, $approved->artifacts());

        $repository = $this->directory();
        file_put_contents($repository . '/composer.json', '{"name":"bedriox/data"}');
        $approved->applyTo($repository);
        self::assertFileExists($repository . '/manifests/bedrock-1.26.60-protocol-2200-schema-2.json');
        foreach ($approved->artifacts() as $artifact) {
            self::assertSame(
                $artifact->record()->sha256(),
                hash_file('sha256', $repository . '/' . $artifact->record()->path()),
            );
        }

        $this->expectException(RuntimeException::class);
        $approved->applyTo($repository);
    }

    public function testChangedArtifactApprovalAndUnlistedFilesFailClosed(): void
    {
        $validator = new ReleaseBundleAdmissionValidator();

        $changed = $this->bundle();
        file_put_contents($changed . '/artifacts/synthetic/block_palette.bin', 'changed');
        try {
            $validator->validate($changed);
            self::fail('Changed release artifact was accepted.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $approval = $this->bundle();
        $manifestPath = $approval . '/dataset-manifest.json';
        $document = self::document($manifestPath);
        $approvalData = $document['approval'] ?? null;
        self::assertIsArray($approvalData);
        $approvalData['contentSha256'] = str_repeat('0', 64);
        $document['approval'] = $approvalData;
        file_put_contents($manifestPath, self::json($document));
        try {
            $validator->validate($approval);
            self::fail('Mismatched release approval was accepted.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $unlisted = $this->bundle();
        file_put_contents($unlisted . '/artifacts/synthetic/not-listed.bin', 'unexpected');
        try {
            $validator->validate($unlisted);
            self::fail('Unlisted release artifact was accepted.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    public function testMissingRequiredArtifactTraversalAndUnapprovedManifestFailClosed(): void
    {
        $validator = new ReleaseBundleAdmissionValidator();
        $missing = $this->bundle();
        $manifestPath = $missing . '/dataset-manifest.json';
        $document = self::document($manifestPath);
        $artifacts = $document['artifacts'] ?? null;
        self::assertIsArray($artifacts);
        array_shift($artifacts);
        $document['artifacts'] = $artifacts;
        self::resign($document);
        file_put_contents($manifestPath, self::json($document));
        try {
            $validator->validate($missing);
            self::fail('Bundle missing a required artifact was accepted.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $traversal = $this->bundle();
        $manifestPath = $traversal . '/dataset-manifest.json';
        $document = self::document($manifestPath);
        $artifacts = $document['artifacts'] ?? null;
        self::assertIsArray($artifacts);
        $first = $artifacts[0] ?? null;
        self::assertIsArray($first);
        $first['path'] = '../outside.bin';
        $artifacts[0] = $first;
        $document['artifacts'] = $artifacts;
        self::resign($document);
        file_put_contents($manifestPath, self::json($document));
        try {
            $validator->validate($traversal);
            self::fail('Traversal artifact path was accepted.');
        } catch (\InvalidArgumentException|RuntimeException) {
            self::addToAssertionCount(1);
        }

        $unapproved = $this->bundle();
        $manifestPath = $unapproved . '/dataset-manifest.json';
        $document = self::document($manifestPath);
        $document['approval'] = null;
        file_put_contents($manifestPath, self::json($document));
        $this->expectException(RuntimeException::class);
        $validator->validate($unapproved);
    }

    public function testDistinctLegacyNamespacesAreAdmitted(): void
    {
        $bundle = $this->bundleWithLegacyData(false);
        $approved = (new ReleaseBundleAdmissionValidator())->validate($bundle);

        self::assertArrayHasKey('legacy_block_ids', $approved->artifacts());
        self::assertArrayHasKey('legacy_item_ids', $approved->artifacts());
    }

    public function testRuntimeIdsCannotBeAdmittedAsLegacyItemIds(): void
    {
        $bundle = $this->bundleWithLegacyData(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('appears to contain runtime/network IDs');
        (new ReleaseBundleAdmissionValidator())->validate($bundle);
    }

    public function testDuplicateLegacyItemIdsFailClosed(): void
    {
        $bundle = $this->bundleWithLegacyData(false, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid or duplicate numeric ID');
        (new ReleaseBundleAdmissionValidator())->validate($bundle);
    }

    public function testInvalidLegacyBlockIdFailsClosed(): void
    {
        $bundle = $this->bundleWithLegacyData(false, false, -1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid identifier or numeric ID');
        (new ReleaseBundleAdmissionValidator())->validate($bundle);
    }

    private function bundle(): string
    {
        $root = $this->directory();
        self::assertTrue(mkdir($root . '/artifacts'));
        self::assertTrue(mkdir($root . '/artifacts/synthetic'));
        $names = [
            'biome_definitions',
            'block_item_mappings',
            'block_palette',
            'block_properties',
            'change_report',
            'creative_inventory',
            'data_driven_blocks',
            'entity_identifiers',
            'item_components',
            'runtime_item_states',
            'voxel_shapes',
        ];
        $artifacts = [];
        foreach ($names as $name) {
            $contents = 'synthetic:' . $name;
            $path = 'artifacts/synthetic/' . $name . '.bin';
            file_put_contents($root . '/' . $path, $contents);
            $artifacts[] = ['name' => $name, 'path' => $path, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
        }
        $document = [
            'schema' => 2,
            'edition' => 'bedrock',
            'gameVersion' => '1.26.60',
            'qualifiedClientVersion' => '1.26.61',
            'protocolVersion' => 2_200,
            'source' => [
                'uri' => 'https://example.invalid/source',
                'commit' => str_repeat('a', 40),
                'retrievedAt' => '2026-09-24T12:00:00Z',
                'rightsHolder' => 'Synthetic test fixture',
                'license' => 'LicenseRef-Synthetic-Test',
            ],
            'generator' => ['name' => 'DataBuilder', 'version' => '1.0.0', 'revision' => str_repeat('b', 40)],
            'approval' => [
                'reviewedBy' => 'test-reviewer',
                'approvedAt' => '2026-09-24T12:01:00Z',
                'redistributionApproved' => true,
                'contentSha256' => '',
            ],
            'artifacts' => $artifacts,
        ];
        self::resign($document);
        file_put_contents($root . '/dataset-manifest.json', self::json($document));
        return $root;
    }

    private function bundleWithLegacyData(bool $runtimeIds, bool $duplicateIds = false, int $blockId = 1): string
    {
        $root = $this->bundle();
        $manifestPath = $root . '/dataset-manifest.json';
        $document = self::document($manifestPath);
        $artifacts = $document['artifacts'] ?? null;
        self::assertIsArray($artifacts);

        $runtimeItems = [];
        $numericIds = [];
        for ($id = 1; $id <= 40; ++$id) {
            $identifier = 'minecraft:test_item_' . $id;
            $runtimeItems[] = ['componentBased' => false, 'id' => $id, 'name' => $identifier, 'version' => 2];
            $numericIds[$identifier] = $duplicateIds ? 257 : ($runtimeIds ? $id : $id + 256);
        }
        $contentsByName = [
            'legacy_block_ids' => self::json(['minecraft:stone' => $blockId]),
            'legacy_item_ids' => self::json(['complex' => [], 'numericIds' => $numericIds, 'schema' => 1, 'simple' => []]),
            'runtime_item_states' => self::json($runtimeItems),
        ];
        foreach ($contentsByName as $name => $contents) {
            $found = false;
            foreach ($artifacts as &$artifact) {
                if (is_array($artifact) && ($artifact['name'] ?? null) === $name) {
                    $artifactPath = $artifact['path'] ?? null;
                    self::assertIsString($artifactPath);
                    file_put_contents($root . '/' . $artifactPath, $contents);
                    $artifact['size'] = strlen($contents);
                    $artifact['sha256'] = hash('sha256', $contents);
                    $found = true;
                    break;
                }
            }
            unset($artifact);
            if (!$found) {
                $path = 'artifacts/synthetic/' . $name . '.json';
                file_put_contents($root . '/' . $path, $contents);
                $artifacts[] = ['name' => $name, 'path' => $path, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
            }
        }
        usort($artifacts, static function (mixed $left, mixed $right): int {
            self::assertIsArray($left);
            self::assertIsArray($right);
            $leftName = $left['name'] ?? null;
            $rightName = $right['name'] ?? null;
            self::assertIsString($leftName);
            self::assertIsString($rightName);
            return strcmp($leftName, $rightName);
        });
        $document['artifacts'] = $artifacts;
        self::resign($document);
        file_put_contents($manifestPath, self::json($document));

        return $root;
    }

    /** @return array<array-key, mixed> */
    private static function document(string $path): array
    {
        $document = json_decode((string) file_get_contents($path), true, 24, JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        return $document;
    }

    /** @param array<array-key, mixed> $document */
    private static function resign(array &$document): void
    {
        $input = '';
        $artifacts = $document['artifacts'] ?? [];
        self::assertIsArray($artifacts);
        foreach ($artifacts as $artifact) {
            self::assertIsArray($artifact);
            $name = $artifact['name'] ?? null;
            $path = $artifact['path'] ?? null;
            $size = $artifact['size'] ?? null;
            $sha256 = $artifact['sha256'] ?? null;
            self::assertIsString($name);
            self::assertIsString($path);
            self::assertIsInt($size);
            self::assertIsString($sha256);
            $input .= $name . "\0" . $path . "\0" . $size . "\0" . $sha256 . "\n";
        }
        self::assertIsArray($document['approval']);
        $document['approval']['contentSha256'] = hash('sha256', $input);
    }

    /** @param array<array-key, mixed> $document */
    private static function json(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/bedriox-bundle-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $this->directories[] = $directory;
        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
