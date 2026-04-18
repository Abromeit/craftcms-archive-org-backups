<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\helpers\ArchiveOrgParser;

final class ArchiveOrgParserTest extends TestCase
{
    public function testExtractsJobIdFromSavePageMarkup(): void
    {
        $body = 'spn.watchJob("job-123", "https://web-static.archive.org/_static/")';

        self::assertSame('job-123', ArchiveOrgParser::extractJobId($body));
    }

    public function testDetectsObservedDailyLimit(): void
    {
        $body = 'You cannot make more than (150) captures per day.';

        self::assertSame(150, ArchiveOrgParser::detectDailyLimit($body));
    }

    public function testExtractsLatestCdxCapture(): void
    {
        $payload = [
            ['timestamp', 'original'],
            ['20260417120000', 'https://example.com/'],
        ];

        self::assertSame(
            [
                'timestamp' => '20260417120000',
                'original' => 'https://example.com/',
            ],
            ArchiveOrgParser::extractLatestCdxCapture($payload)
        );
    }
}
