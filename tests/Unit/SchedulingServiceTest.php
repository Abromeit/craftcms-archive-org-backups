<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\services\SchedulingService;

final class SchedulingServiceTest extends TestCase
{
    public function testNeverSubmittedTargetsReceiveHighestPriority(): void
    {
        self::assertSame(
            ArchiveOrgBackups::PRIORITY_NEVER_SUBMITTED,
            SchedulingService::calculatePriority(null, '2026-04-17 12:00:00')
        );
    }

    public function testChangedTargetsReceiveChangedPriority(): void
    {
        self::assertSame(
            ArchiveOrgBackups::PRIORITY_CHANGED,
            SchedulingService::calculatePriority('2026-04-16 12:00:00', '2026-04-17 12:00:00')
        );
    }

    public function testChangedTargetsRespectConfiguredResubmitWindow(): void
    {
        $before = time();
        $nextSubmissionAt = SchedulingService::calculateNextSubmissionAt(
            '2026-04-16 12:00:00',
            '2026-04-16 12:00:00',
            '2026-04-17 12:00:00',
            7,
            24
        );
        $after = time();

        self::assertGreaterThanOrEqual($before + 86340, $nextSubmissionAt->getTimestamp());
        self::assertLessThanOrEqual($after + 86460, $nextSubmissionAt->getTimestamp());
    }

    public function testLiveCandidateSelectionPrefersOldestRemoteCheck(): void
    {
        $rows = [
            ['id' => 4, 'lastRemoteCheckAt' => '2026-04-17 12:10:00'],
            ['id' => 2, 'lastRemoteCheckAt' => '2026-04-17 12:00:00'],
            ['id' => 9, 'lastRemoteCheckAt' => null],
        ];

        self::assertSame(
            ['id' => 9, 'lastRemoteCheckAt' => null],
            SchedulingService::selectLiveCandidate($rows)
        );
    }

    public function testFirstConfirmationDelayRunsAfterThirtySeconds(): void
    {
        $delay = (new SchedulingService())->getConfirmationDelay(0);

        self::assertSame(30, $delay);
    }

    public function testFirstStatusPollRunsAboutThirtySecondsAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(0);

        self::assertGreaterThanOrEqual(30, $delay);
        self::assertLessThanOrEqual(60, $delay);
    }

    public function testSecondStatusPollRunsAboutSixtySecondsAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(1);

        self::assertGreaterThanOrEqual(30, $delay);
        self::assertLessThanOrEqual(60, $delay);
    }

    public function testThirdStatusPollRunsAboutFiveMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(2);

        self::assertGreaterThanOrEqual(240, $delay);
        self::assertLessThanOrEqual(270, $delay);
    }

    public function testFourthStatusPollRunsAboutTenMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(3);

        self::assertGreaterThanOrEqual(300, $delay);
        self::assertLessThanOrEqual(330, $delay);
    }

    public function testFifthStatusPollRunsAboutFifteenMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(4);

        self::assertGreaterThanOrEqual(300, $delay);
        self::assertLessThanOrEqual(330, $delay);
    }

    public function testSixthStatusPollRunsAboutThirtyMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(5);

        self::assertGreaterThanOrEqual(900, $delay);
        self::assertLessThanOrEqual(930, $delay);
    }

    public function testSeventhStatusPollRunsAboutFortyFiveMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(6);

        self::assertGreaterThanOrEqual(900, $delay);
        self::assertLessThanOrEqual(930, $delay);
    }

    public function testEighthStatusPollRunsAboutSixtyMinutesAfterSubmission(): void
    {
        $delay = (new SchedulingService())->getStatusPollDelay(7);

        self::assertGreaterThanOrEqual(900, $delay);
        self::assertLessThanOrEqual(930, $delay);
    }
}
