<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;

final class LiveWatchService extends Component
{
    private const BUDGET_CACHE_KEY = 'archive-org-backups:live-watch:last-remote-check';

    private const VIEWER_CACHE_PREFIX = 'archive-org-backups:viewer:';

    public function registerHeartbeat(string $viewerToken): void
    {
        Craft::$app->getCache()->set(
            self::VIEWER_CACHE_PREFIX . $viewerToken,
            time(),
            300
        );
    }

    /**
     * @param int[] $visibleTargetIds
     */
    public function tick(string $viewerToken, array $visibleTargetIds): void
    {
        if ($viewerToken === '' || !$this->hasActiveViewer($viewerToken)) {
            return;
        }

        if ($visibleTargetIds === []) {
            return;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = 'archive-org-backups:live-watch-budget';

        if (!$mutex->acquire($lockName, 0)) {
            return;
        }

        try {
            $lastRun = (int) (Craft::$app->getCache()->get(self::BUDGET_CACHE_KEY) ?: 0);

            if (($lastRun + 60) > time()) {
                return;
            }

            $rows = ArchiveOrgBackups::plugin()->getTargets()->getPendingLiveCandidates($visibleTargetIds);
            $candidate = SchedulingService::selectLiveCandidate($rows);

            if ($candidate === null) {
                return;
            }

            Craft::$app->getCache()->set(self::BUDGET_CACHE_KEY, time(), 120);
            ArchiveOrgBackups::plugin()->getIndexing()->confirmIndexing(
                (int) $candidate['id'],
                0,
                true,
                $candidate['lastJobId'] ?? null
            );
        } finally {
            $mutex->release($lockName);
        }
    }

    private function hasActiveViewer(string $viewerToken): bool
    {
        return Craft::$app->getCache()->get(self::VIEWER_CACHE_PREFIX . $viewerToken) !== false;
    }
}
