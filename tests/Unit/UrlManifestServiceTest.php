<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\services\UrlManifestService;

final class UrlManifestServiceTest extends TestCase
{
    public function testExcludedRobotsDirectivesMatchNoindexNoneAndNoarchive(): void
    {
        self::assertTrue(UrlManifestService::containsExcludedRobotsDirectives('noindex'));
        self::assertTrue(UrlManifestService::containsExcludedRobotsDirectives('none'));
        self::assertTrue(UrlManifestService::containsExcludedRobotsDirectives('index, follow, noarchive'));
    }

    public function testNonExcludedRobotsDirectivesRemainTrackable(): void
    {
        self::assertFalse(UrlManifestService::containsExcludedRobotsDirectives(null));
        self::assertFalse(UrlManifestService::containsExcludedRobotsDirectives(''));
        self::assertFalse(UrlManifestService::containsExcludedRobotsDirectives('index, follow'));
    }
}
