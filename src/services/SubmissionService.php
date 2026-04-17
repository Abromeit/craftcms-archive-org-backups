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
    private ArchiveOrgClientInterface $client;

    public function init(): void
    {
        parent::init();
        $this->client = new PublicArchiveOrgClient();
    }

    public function processDueTargets(): int
    {
        $quota = ArchiveOrgBackups::plugin()->getQuota();

        if ($quota->isQuotaExhausted()) {
            return 0;
        }

        $limit = max(0, min(25, $quota->getRemainingBudget()));

        if ($limit === 0) {
            return 0;
        }

        $submitted = 0;

        foreach (ArchiveOrgBackups::plugin()->getTargets()->getDueTargets($limit) as $target) {
            if ($this->submitTarget($target)) {
                ++$submitted;
                continue;
            }

            if ($quota->isQuotaExhausted()) {
                break;
            }
        }

        return $submitted;
    }

    public function submitTarget(ArchiveTargetRecord $target): bool
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

            return false;
        } catch (TemporaryArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionFailure(
                $target,
                $exception->getMessage(),
                'retry',
                Db::prepareDateForDb((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 hour'))
            );

            return false;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateSubmissionFailure(
                $target,
                $exception->getMessage(),
                'failed',
                Db::prepareDateForDb((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 day'))
            );

            return false;
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
            ]));

        return true;
    }
}
