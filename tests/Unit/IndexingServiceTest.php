<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\services\IndexingService;

final class IndexingServiceTest extends TestCase
{
    public function testSnapshotIsCurrentWhenCaptureTimestampMatchesSubmissionWindow(): void
    {
        self::assertTrue(
            IndexingService::isSnapshotCurrent(
                '2026-04-17 12:00:00',
                '20260417115800',
                null
            )
        );
    }

    public function testSnapshotIsNotCurrentWithoutRecentCapture(): void
    {
        self::assertFalse(
            IndexingService::isSnapshotCurrent(
                '2026-04-17 12:00:00',
                '20260416120000',
                null
            )
        );
    }
}
