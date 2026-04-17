<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use DateTimeImmutable;
use DateTimeZone;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\records\ArchiveAttemptRecord;

final class QuotaService extends Component
{
    private const CACHE_KEY = 'archive-org-backups:quota-exhausted-until';

    public static function buildProgress(int $used, int $limit, string $windowLabel): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => SchedulingService::percentage($used, $limit),
            'windowLabel' => $windowLabel,
        ];
    }

    public function getDailyLimit(): int
    {
        return ArchiveOrgBackups::plugin()->getSettings()->publicDailyLimit;
    }

    public function getDailyUsage(?DateTimeImmutable $now = null): int
    {
        [$start, $end] = $this->getWindow($now);

        return (int) (new Query())
            ->from(ArchiveAttemptRecord::tableName())
            ->where([
                'type' => 'submit',
                'outcome' => 'accepted',
            ])
            ->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($start)])
            ->andWhere(['<', 'dateCreated', Db::prepareDateForDb($end)])
            ->count();
    }

    public function getRemainingBudget(?DateTimeImmutable $now = null): int
    {
        $remaining = $this->getDailyLimit() - $this->getDailyUsage($now);

        return max(0, $remaining);
    }

    public function isQuotaExhausted(?DateTimeImmutable $now = null): bool
    {
        $until = Craft::$app->getCache()->get(self::CACHE_KEY);

        if (is_string($until) && strtotime($until) > time()) {
            return true;
        }

        return $this->getRemainingBudget($now) <= 0;
    }

    public function markQuotaExhausted(?int $observedLimit = null): void
    {
        $resetAt = ArchiveOrgBackups::plugin()->getScheduling()->getQuotaResetAt();
        $ttl = max(60, $resetAt->getTimestamp() - time());

        Craft::$app->getCache()->set(self::CACHE_KEY, Db::prepareDateForDb($resetAt), $ttl);

        if ($observedLimit !== null) {
            ArchiveOrgBackups::plugin()->getTargets()->addAttempt(
                null,
                'submit',
                'quota_exhausted',
                'Archive.org quota exhausted.',
                null,
                $observedLimit,
                null
            );
        }
    }

    public function getProgressData(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(Craft::$app->getTimeZone()));
        $used = $this->getDailyUsage($now);
        $limit = $this->getDailyLimit();

        return self::buildProgress(
            $used,
            $limit,
            $now->format('Y-m-d')
        );
    }

    /**
     * @return array{0:DateTimeImmutable, 1:DateTimeImmutable}
     */
    private function getWindow(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(Craft::$app->getTimeZone());
        $current = $now ?? new DateTimeImmutable('now', $timezone);
        $start = $current->setTimezone($timezone)->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
        $end = $start->modify('+1 day');

        return [$start, $end];
    }
}
