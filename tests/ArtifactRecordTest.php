<?php

declare(strict_types=1);

namespace Bedriox\Data\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Bedriox\Data\ArtifactRecord;

final class ArtifactRecordTest extends TestCase
{
    public function testRecordVerifiesContents(): void
    {
        $record = new ArtifactRecord(
            path: 'example/fixture.bin',
            sha256: hash('sha256', 'fixture'),
            sourceUri: 'https://example.invalid/fixture',
            retrievedAt: '2026-09-14',
            rightsHolder: 'Example rights holder',
            licenseExpression: 'Apache-2.0',
            generator: 'manual-test-fixture',
            reviewedBy: 'test-reviewer',
            redistributionApproved: true,
        );

        self::assertTrue($record->matchesContents('fixture'));
        self::assertFalse($record->matchesContents('changed'));
    }

    public function testTraversalPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArtifactRecord(
            path: '../secret',
            sha256: str_repeat('0', 64),
            sourceUri: 'https://example.invalid/source',
            retrievedAt: '2026-09-14',
            rightsHolder: 'Example rights holder',
            licenseExpression: 'Apache-2.0',
            generator: 'test',
            reviewedBy: 'test-reviewer',
            redistributionApproved: false,
        );
    }

    public function testWindowsAlternateStreamPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArtifactRecord(
            path: 'review/file.json:stream',
            sha256: str_repeat('0', 64),
            sourceUri: 'https://example.invalid/source',
            retrievedAt: '2026-09-14',
            rightsHolder: 'Example rights holder',
            licenseExpression: 'Apache-2.0',
            generator: 'test',
            reviewedBy: 'test-reviewer',
            redistributionApproved: false,
        );
    }

    public function testReviewDecisionIsPreserved(): void
    {
        $record = new ArtifactRecord(
            path: 'review/pending.bin',
            sha256: str_repeat('0', 64),
            sourceUri: 'https://example.invalid/pending',
            retrievedAt: '2026-09-14',
            rightsHolder: 'Example rights holder',
            licenseExpression: 'LicenseRef-Pending',
            generator: 'manual',
            reviewedBy: 'test-reviewer',
            redistributionApproved: false,
        );

        self::assertFalse($record->redistributionApproved);
    }

    public function testBlankReviewMetadataIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArtifactRecord(
            path: 'review/invalid.bin',
            sha256: str_repeat('0', 64),
            sourceUri: 'https://example.invalid/invalid',
            retrievedAt: '2026-09-14',
            rightsHolder: ' ',
            licenseExpression: 'Apache-2.0',
            generator: 'manual',
            reviewedBy: 'test-reviewer',
            redistributionApproved: true,
        );
    }
}
