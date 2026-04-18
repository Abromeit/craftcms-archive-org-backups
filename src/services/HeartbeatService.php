<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\jobs\HeartbeatJob;
use abromeit\archiveorgbackups\jobs\ProbeExternalSnapshotsJob;
use abromeit\archiveorgbackups\jobs\SubmitDueTargetsJob;
use abromeit\archiveorgbackups\jobs\SyncTargetsJob;

final class HeartbeatService extends Component
{
    private const CACHE_KEY = 'archive-org-backups:heartbeat-scheduled';

    private const LAST_RUN_CACHE_KEY = 'archive-org-backups:heartbeat-last-run';

    private const LOCK_KEY = 'archive-org-backups:heartbeat-schedule';

    public static function shouldRunMaintenance(int $lastRunAt, int $now): bool
    {
        return ($lastRunAt + 30) <= $now;
    }

    public static function shouldScheduleHeartbeat(int $scheduledUntil, int $now, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        return $scheduledUntil <= $now;
    }

    public function ensureScheduled(bool $force = false): void
    {
        if (!ArchiveOrgBackups::isOutboundEnabled()) {
            return;
        }

        $cache = Craft::$app->getCache();
        $mutex = Craft::$app->getMutex();
        $now = time();

        if (!$mutex->acquire(self::LOCK_KEY, 0)) {
            return;
        }

        try {
            $scheduledUntil = (int) ($cache->get(self::CACHE_KEY) ?: 0);

            if (!self::shouldScheduleHeartbeat($scheduledUntil, $now, $force)) {
                return;
            }

            $this->scheduleHeartbeat($force ? 0 : $this->getInterval(), $now);
        } finally {
            $mutex->release(self::LOCK_KEY);
        }
    }

    public function runMaintenance(): void
    {
        if (!ArchiveOrgBackups::isOutboundEnabled()) {
            return;
        }

        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::LOCK_KEY, 0)) {
            return;
        }

        try {
            $cache = Craft::$app->getCache();
            $now = time();
            $lastRunAt = (int) ($cache->get(self::LAST_RUN_CACHE_KEY) ?: 0);

            if (!self::shouldRunMaintenance($lastRunAt, $now)) {
                return;
            }

            $cache->set(self::LAST_RUN_CACHE_KEY, $now, 120);
            Craft::$app->getQueue()->push(new SyncTargetsJob());
            Craft::$app->getQueue()->push(new SubmitDueTargetsJob());
            Craft::$app->getQueue()->push(new ProbeExternalSnapshotsJob());
            $this->scheduleHeartbeat($this->getInterval(), $now);
        } finally {
            $mutex->release(self::LOCK_KEY);
        }
    }

    private function getInterval(): int
    {
        return max(60, ArchiveOrgBackups::plugin()->getSettings()->heartbeatIntervalMinutes * 60);
    }

    private function scheduleHeartbeat(int $delay, int $now): void
    {
        $grace = max(300, (int) ceil(max(60, $delay) / 2));
        $ttl = max(600, $delay + ($grace * 2));

        Craft::$app->getCache()->set(self::CACHE_KEY, $now + $delay + $grace, $ttl);
        Craft::$app->getQueue()->delay($delay)->push(new HeartbeatJob());
    }
}
