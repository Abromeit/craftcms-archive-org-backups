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
        putenv(ArchiveOrgEndpoints::CDX_BASE_URL_ENV);

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
    }

    public function testSpecificOverridesCanBeSetIndividually(): void
    {
        putenv(ArchiveOrgEndpoints::SAVE_BASE_URL_ENV . '=http://save.local/');
        putenv(ArchiveOrgEndpoints::CDX_BASE_URL_ENV . '=http://cdx.local/');

        self::assertSame(
            'http://save.local/save/',
            ArchiveOrgEndpoints::saveUrl()
        );

        self::assertSame(
            'http://cdx.local/cdx/search/cdx?url=https%3A%2F%2Fexample.com&limit=1',
            ArchiveOrgEndpoints::cdxUrl([
                'url' => 'https://example.com',
                'limit' => 1,
            ])
        );

        self::assertSame(
            'http://cdx.local/web/20260417120000/https://example.com/',
            ArchiveOrgEndpoints::snapshotUrl('20260417120000', 'https://example.com/')
        );
    }
}
