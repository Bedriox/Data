<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Bedriox\Data\AdmittedDataManifest;
use Bedriox\Data\AdmittedRegistryDataSet;
use Bedriox\Data\BedrockVersion;

final class AdmittedDataManifestTest extends TestCase
{
    public function testBundledManifestIsTheArtifactMetadataAuthority(): void
    {
        $manifest = AdmittedDataManifest::bundled();
        self::assertSame(AdmittedRegistryDataSet::artifactNames(), array_keys($manifest->artifacts()));
        foreach ($manifest->artifacts() as $name => $artifact) {
            self::assertSame($name, $artifact->name());
            self::assertGreaterThan(0, $artifact->size());
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $artifact->sha256());
            self::assertNotSame('', AdmittedRegistryDataSet::bundled()->artifact($name));
        }
    }

    public function testManifestRejectsTraversalDuplicateAndMalformedMetadata(): void
    {
        $base = json_decode((string) file_get_contents(dirname(__DIR__) . '/' . BedrockVersion::ADMISSION_MANIFEST), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($base);
        $cases = [];
        $traversal = $base;
        $artifacts = $traversal['artifacts'] ?? null;
        self::assertIsArray($artifacts);
        $first = $artifacts[0] ?? null;
        self::assertIsArray($first);
        $first['path'] = '../outside';
        $artifacts[0] = $first;
        $traversal['artifacts'] = $artifacts;
        $cases[] = $traversal;
        $duplicate = $base;
        $artifacts = $duplicate['artifacts'] ?? null;
        self::assertIsArray($artifacts);
        $first = $artifacts[0] ?? null;
        self::assertIsArray($first);
        $artifacts[] = $first;
        $duplicate['artifacts'] = $artifacts;
        $cases[] = $duplicate;
        $unapproved = $base;
        $approval = $unapproved['approval'] ?? null;
        self::assertIsArray($approval);
        $approval['redistributionApproved'] = false;
        $unapproved['approval'] = $approval;
        $cases[] = $unapproved;
        $badHash = $base;
        $artifacts = $badHash['artifacts'] ?? null;
        self::assertIsArray($artifacts);
        $first = $artifacts[0] ?? null;
        self::assertIsArray($first);
        $first['sha256'] = 'invalid';
        $artifacts[0] = $first;
        $badHash['artifacts'] = $artifacts;
        $cases[] = $badHash;

        foreach ($cases as $case) {
            $path = tempnam(sys_get_temp_dir(), 'bedriox-manifest-');
            self::assertIsString($path);
            file_put_contents($path, json_encode($case, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            try {
                AdmittedDataManifest::fromFile($path);
                self::fail('Invalid admission manifest was accepted.');
            } catch (InvalidArgumentException|RuntimeException) {
                self::addToAssertionCount(1);
            } finally {
                @unlink($path);
            }
        }
    }
}
