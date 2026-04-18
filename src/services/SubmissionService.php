<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\archiveorg\ArchiveOrgClientInterface;
use abromeit\archiveorgbackups\archiveorg\PublicArchiveOrgClient;
use abromeit\archiveorgbackups\archiveorg\exceptions\ArchiveOrgException;
use abromeit\archiveorgbackups\archiveorg\exceptions\QuotaExhaustedException;
use abromeit\archiveorgbackups\archiveorg\exceptions\TemporaryArchiveOrgException;
use abromeit\archiveorgbackups\jobs\PollSubmissionStatusJob;
use abromeit\archiveorgbackups\records\ArchiveTargetRecord;

final class SubmissionService extends Component
{
    public const SUBMIT_ACCEPTED = 'accepted';

    public const SUBMIT_PERMANENT_FAILURE = 'permanent_failure';

    public const SUBMIT_TEMPORARY_FAILURE = 'temporary_failure';

    public const SUBMIT_QUOTA_EXHAUSTED = 'quota_exhausted';

    private const LOCK_KEY = 'archive-org-backups:submit-due-targets';

    private const STALE_PENDING_RECOVERY_AGE = 1800;

    // Matches the official Wayback Machine WordPress plugin's per-tick batch
    // (iawmlf_scan_own_posts_per_call = 10).
    private const BATCH_SIZE = 10;

    private ArchiveOrgClientInterface $client;

    public function init(): void
    {
        parent::init();
        $this->client = new PublicArchiveOrgClient();
    }

    public function processDueTargets(): int
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::LOCK_KEY, 0)) {
            return 0;
        }

        try {
            return $this->processDueTargetsInternal();
        } finally {
            $mutex->release(self::LOCK_KEY);
        }
    }

    private function processDueTargetsInternal(): int
    {
        $this->recoverStalePendingTargets();

        $quota = ArchiveOrgBackups::plugin()->getQuota();

        if ($quota->isQuotaExhausted()) {
            return 0;
        }

        $limit = max(0, min(self::BATCH_SIZE, $quota->getRemainingBudget()));

        if ($limit === 0) {
            return 0;
        }

        $submitted = 0;

        foreach (ArchiveOrgBackups::plugin()->getTargets()->getDueTargets($limit) as $target) {
            $outcome = $this->submitTarget($target);

            if ($outcome === self::SUBMIT_ACCEPTED) {
                ++$submitted;
                continue;
            }

            // Stop the batch as soon as Archive.org signals back-off (429/5xx
            // or exhausted daily quota). Remaining targets stay due and are
            // retried on the next heartbeat once SPN jobs have drained.
            if (
                $outcome === self::SUBMIT_TEMPORARY_FAILURE
                || $outcome === self::SUBMIT_QUOTA_EXHAUSTED
            ) {
                break;
            }
        }

        return $submitted;
    }

    private function recoverStalePendingTargets(): void
    {
        $targets = ArchiveOrgBackups::plugin()->getTargets()->getStalePendingTargets(
            self::BATCH_SIZE,
            self::STALE_PENDING_RECOVERY_AGE
        );

        foreach ($targets as $target) {
            if ($target->lastJobId === null) {
                continue;
            }

            Craft::$app->getQueue()
                ->push(new PollSubmissionStatusJob([
                    'targetId' => (int) $target->id,
                    'attempt' => 0,
                    'expectedJobId' => $target->lastJobId,
                ]));
        }
    }

    public function submitTarget(ArchiveTargetRecord $target): string
    {
        try {
            $result = $this->client->submitUrl($target->url);
        } catch (QuotaExhaustedException $exception) {
            ArchiveOrgBackups::plugin()->getQuota()->markQuotaExhausted($exception->observedLimit);
            ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionFailure(
                $target,
                $exception->getMessage(),
                'quota_exhausted',
                Db::prepareDateForDb(ArchiveOrgBackups::plugin()->getScheduling()->getQuotaResetAt()),
                $exception->observedLimit
            );

            return self::SUBMIT_QUOTA_EXHAUSTED;
        } catch (TemporaryArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionFailure(
                $target,
                $exception->getMessage(),
                'retry',
                Db::prepareDateForDb((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 hour'))
            );

            return self::SUBMIT_TEMPORARY_FAILURE;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionFailure(
                $target,
                $exception->getMessage(),
                'failed',
                Db::prepareDateForDb((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 day'))
            );

            return self::SUBMIT_PERMANENT_FAILURE;
        }

        ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionAccepted(
            $target,
            $result['jobId'],
            $result['observedDailyLimit']
        );

        Craft::$app->getQueue()
            ->delay(ArchiveOrgBackups::plugin()->getScheduling()->getStatusPollDelay(0))
            ->push(new PollSubmissionStatusJob([
                'targetId' => (int) $target->id,
                'attempt' => 0,
                'expectedJobId' => $result['jobId'],
            ]));

        return self::SUBMIT_ACCEPTED;
    }
}
