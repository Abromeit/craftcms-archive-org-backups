<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\services\HeartbeatService;

final class HeartbeatServiceTest extends TestCase
{
    public function testScheduleGuardSkipsActiveHeartbeat(): void
    {
        self::assertFalse(HeartbeatService::shouldScheduleHeartbeat(1_000, 900));
    }

    public function testScheduleGuardAllowsExpiredHeartbeat(): void
    {
        self::assertTrue(HeartbeatService::shouldScheduleHeartbeat(900, 900));
    }

    public function testMaintenanceGuardAllowsRunsAfterCooldown(): void
    {
        self::assertTrue(HeartbeatService::shouldRunMaintenance(869, 900));
    }

    public function testMaintenanceGuardBlocksImmediateDuplicates(): void
    {
        self::assertFalse(HeartbeatService::shouldRunMaintenance(880, 900));
    }
}
