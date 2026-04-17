<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\services\QuotaService;

final class QuotaServiceTest extends TestCase
{
    public function testBuildProgressCalculatesPercentage(): void
    {
        self::assertSame(
            [
                'used' => 75,
                'limit' => 150,
                'percent' => 50,
                'windowLabel' => '2026-04-17',
            ],
            QuotaService::buildProgress(75, 150, '2026-04-17')
        );
    }
}
