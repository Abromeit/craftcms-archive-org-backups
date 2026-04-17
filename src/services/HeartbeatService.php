<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\jobs\HeartbeatJob;
use abromeit\archiveorgbackups\jobs\SubmitDueTargetsJob;
use abromeit\archiveorgbackups\jobs\SyncTargetsJob;

final class HeartbeatService extends Component
{
    private const CACHE_KEY = 'archive-org-backups:heartbeat-scheduled';

    private const LOCK_KEY = 'archive-org-backups:heartbeat-schedule';

    public function ensureScheduled(bool $force = false): void
    {
        $interval = max(60, ArchiveOrgBackups::plugin()->getSettings()->heartbeatIntervalMinutes * 60);
        $cache = Craft::$app->getCache();
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::LOCK_KEY, 0)) {
            return;
        }

        try {
            $scheduledUntil = (int) ($cache->get(self::CACHE_KEY) ?: 0);

            if (!$force && $scheduledUntil > time()) {
                return;
            }

            $delay = $force ? 0 : $interval;
            $cache->set(self::CACHE_KEY, time() + $interval, $interval * 2);
            Craft::$app->getQueue()->delay($delay)->push(new HeartbeatJob());
        } finally {
            $mutex->release(self::LOCK_KEY);
        }
    }

    public function runMaintenance(): void
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::LOCK_KEY, 0)) {
            return;
        }

        try {
            Craft::$app->getQueue()->push(new SyncTargetsJob());
            Craft::$app->getQueue()->push(new SubmitDueTargetsJob());

            $interval = max(60, ArchiveOrgBackups::plugin()->getSettings()->heartbeatIntervalMinutes * 60);
            Craft::$app->getCache()->set(self::CACHE_KEY, time() + $interval, $interval * 2);
            Craft::$app->getQueue()->delay($interval)->push(new HeartbeatJob());
        } finally {
            $mutex->release(self::LOCK_KEY);
        }
    }
}
