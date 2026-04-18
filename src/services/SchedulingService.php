<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use DateTimeImmutable;
use DateTimeZone;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class SchedulingService extends Component
{
    public static function calculatePriority(
        ?string $lastSubmittedSourceDateUpdated,
        string $sourceDateUpdated
    ): int {
        if ($lastSubmittedSourceDateUpdated === null) {
            return ArchiveOrgBackups::PRIORITY_NEVER_SUBMITTED;
        }

        if (strtotime($sourceDateUpdated) > strtotime($lastSubmittedSourceDateUpdated)) {
            return ArchiveOrgBackups::PRIORITY_CHANGED;
        }

        return ArchiveOrgBackups::PRIORITY_REFRESH;
    }

    public static function isContentChanged(
        ?string $lastSubmittedSourceDateUpdated,
        string $sourceDateUpdated
    ): bool {
        if ($lastSubmittedSourceDateUpdated === null) {
            return true;
        }

        return strtotime($sourceDateUpdated) > strtotime($lastSubmittedSourceDateUpdated);
    }

    public static function calculateNextSubmissionAt(
        ?string $lastSubmittedAt,
        ?string $lastSubmittedSourceDateUpdated,
        string $sourceDateUpdated,
        int $unchangedRefreshDays,
        int $changedResubmitHours
    ): DateTimeImmutable {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($lastSubmittedAt === null) {
            return $now;
        }

        if (self::isContentChanged($lastSubmittedSourceDateUpdated, $sourceDateUpdated)) {
            return $now->modify('+' . $changedResubmitHours . ' hours');
        }

        return (new DateTimeImmutable($lastSubmittedAt, new DateTimeZone('UTC')))
            ->modify('+' . $unchangedRefreshDays . ' days');
    }

    public static function percentage(int $used, int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        return (int) min(100, round(($used / $limit) * 100));
    }

    public static function selectLiveCandidate(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static function(array $left, array $right): int {
            $leftTs = strtotime($left['lastRemoteCheckAt'] ?? '1970-01-01 00:00:00');
            $rightTs = strtotime($right['lastRemoteCheckAt'] ?? '1970-01-01 00:00:00');

            if ($leftTs === $rightTs) {
                return $left['id'] <=> $right['id'];
            }

            return $leftTs <=> $rightTs;
        });

        return $rows[0];
    }

    public function getConfirmationDelay(int $attempt): int
    {
        $delays = [60, 300, 1800, 7200, 21600, 43200, 86400];
        $delay = $delays[$attempt] ?? 86400;

        if ($attempt === 0) {
            return 30;
        }

        return $this->applyJitter($delay, 90);
    }

    public function getStatusPollDelay(int $attempt): int
    {
        // Each entry is the delay after the previous poll, producing a total
        // schedule of 30 seconds, 60 seconds, 5 minutes, 10 minutes,
        // 15 minutes, 30 minutes, 45 minutes, and 60 minutes after
        // submission, then hourly.
        $delays = [
            30,
            30,
            240,
            300,
            300,
            900,
            900,
            900,
            3600,
        ];
        $delay = $delays[$attempt] ?? 3600;

        return $this->applyJitter($delay, 30);
    }

    public function getTemporaryFailureDelay(int $attempt): int
    {
        $delays = [900, 1800, 3600, 7200];
        $delay = $delays[$attempt] ?? 7200;

        return $this->applyJitter($delay, 60);
    }

    public function getQuotaResetAt(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $timezone = new DateTimeZone(Craft::$app->getTimeZone());
        $current = $now ?? new DateTimeImmutable('now', $timezone);

        return $current
            ->setTimezone($timezone)
            ->modify('tomorrow')
            ->setTime(0, 5)
            ->setTimezone(new DateTimeZone('UTC'));
    }

    public function getChangedTargetWindowHours(): int
    {
        return ArchiveOrgBackups::plugin()->getSettings()->changedResubmitHours;
    }

    public function getRetrySubmissionAt(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $current = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $current->modify('+' . $this->getChangedTargetWindowHours() . ' hours');
    }

    public function getUnchangedRefreshDays(): int
    {
        return ArchiveOrgBackups::plugin()->getSettings()->unchangedRefreshDays;
    }

    private function applyJitter(int $seconds, int $jitter): int
    {
        if ($jitter <= 0) {
            return $seconds;
        }

        return $seconds + random_int(0, $jitter);
    }
}
