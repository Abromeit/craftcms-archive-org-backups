<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\archiveorg\ArchiveOrgEndpoints;

final class ArchiveOrgEndpointsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(ArchiveOrgEndpoints::GLOBAL_BASE_URL_ENV);
        putenv(ArchiveOrgEndpoints::SAVE_BASE_URL_ENV);
        putenv(ArchiveOrgEndpoints::SAVE_STATUS_BASE_URL_ENV);

        parent::tearDown();
    }

    public function testGlobalBaseUrlOverridesAllEndpoints(): void
    {
        putenv(ArchiveOrgEndpoints::GLOBAL_BASE_URL_ENV . '=http://127.0.0.1:8080/');

        self::assertSame(
            'http://127.0.0.1:8080/save/',
            ArchiveOrgEndpoints::saveUrl()
        );

        self::assertSame(
            'http://127.0.0.1:8080/save/status/job-123',
            ArchiveOrgEndpoints::saveStatusUrl('job-123')
        );

        self::assertSame(
            'http://127.0.0.1:8080/web/20260417120000/https://example.com/',
            ArchiveOrgEndpoints::snapshotUrl('20260417120000', 'https://example.com/')
        );

        self::assertSame(
            'http://127.0.0.1:8080/web/9999/https://example.com/',
            ArchiveOrgEndpoints::latestCaptureUrl('https://example.com/')
        );
    }

    public function testSpecificOverridesCanBeSetIndividually(): void
    {
        putenv(ArchiveOrgEndpoints::SAVE_BASE_URL_ENV . '=http://save.local/');
        putenv(ArchiveOrgEndpoints::SAVE_STATUS_BASE_URL_ENV . '=http://status.local/');

        self::assertSame(
            'http://save.local/save/',
            ArchiveOrgEndpoints::saveUrl()
        );

        self::assertSame(
            'http://status.local/save/status/job-123',
            ArchiveOrgEndpoints::saveStatusUrl('job-123')
        );
    }

    public function testSnapshotAndLatestCaptureDefaultToWayback(): void
    {
        self::assertSame(
            'https://web.archive.org/web/20260417120000/https://example.com/',
            ArchiveOrgEndpoints::snapshotUrl('20260417120000', 'https://example.com/')
        );

        self::assertSame(
            'https://web.archive.org/web/9999/https://example.com/',
            ArchiveOrgEndpoints::latestCaptureUrl('https://example.com/')
        );
    }
}
