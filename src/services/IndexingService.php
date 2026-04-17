<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use DateTimeImmutable;
use DateTimeZone;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\archiveorg\ArchiveOrgClientInterface;
use abromeit\archiveorgbackups\archiveorg\PublicArchiveOrgClient;
use abromeit\archiveorgbackups\archiveorg\exceptions\ArchiveOrgException;
use abromeit\archiveorgbackups\archiveorg\exceptions\TemporaryArchiveOrgException;
use abromeit\archiveorgbackups\jobs\ConfirmIndexingJob;
use abromeit\archiveorgbackups\records\ArchiveTargetRecord;

final class IndexingService extends Component
{
    private const MAX_STATUS_POLL_ATTEMPTS = 10;

    private const MAX_CONFIRMATION_ATTEMPTS = 10;

    private ArchiveOrgClientInterface $client;

    public static function isSnapshotCurrent(
        ?string $submittedAt,
        ?string $availabilityTimestamp,
        ?string $cdxTimestamp
    ): bool {
        $submitted = $submittedAt !== null ? strtotime($submittedAt) : false;
        $latest = max(
            self::normalizeTimestamp($availabilityTimestamp),
            self::normalizeTimestamp($cdxTimestamp)
        );

        if ($submitted === false || $latest === 0) {
            return false;
        }

        return $latest >= ($submitted - 300);
    }

    private static function normalizeTimestamp(?string $timestamp): int
    {
        if ($timestamp === null || $timestamp === '') {
            return 0;
        }

        if (preg_match('/^\d{14}$/', $timestamp) === 1) {
            $date = DateTimeImmutable::createFromFormat('YmdHis', $timestamp, new DateTimeZone('UTC'));

            return $date instanceof DateTimeImmutable ? $date->getTimestamp() : 0;
        }

        $parsed = strtotime($timestamp);

        return $parsed !== false ? $parsed : 0;
    }

    public function init(): void
    {
        parent::init();
        $this->client = new PublicArchiveOrgClient();
    }

    public function pollSubmissionStatus(int $targetId, int $attempt): void
    {
        $target = ArchiveOrgBackups::plugin()->getTargets()->getTargetById($targetId);

        if (!$target instanceof ArchiveTargetRecord || $target->lastJobId === null) {
            return;
        }

        if ($attempt >= self::MAX_STATUS_POLL_ATTEMPTS) {
            ArchiveOrgBackups::plugin()->getTargets()->updateStatusPoll(
                $target,
                'failed',
                'Maximum Archive.org save-status polling attempts reached.'
            );

            return;
        }

        try {
            $status = $this->client->getSaveStatus($target->lastJobId);
        } catch (TemporaryArchiveOrgException) {
            $this->scheduleStatusPoll($targetId, $attempt + 1, $attempt + 1);
            return;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateStatusPoll(
                $target,
                'failed',
                $exception->getMessage()
            );
            return;
        }

        ArchiveOrgBackups::plugin()->getTargets()->updateStatusPoll(
            $target,
            $status['status'],
            $status['message'],
            $status['statusExt']
        );

        if ($status['status'] === 'pending') {
            $this->scheduleStatusPoll($targetId, $attempt + 1, $attempt + 1);
            return;
        }

        if ($status['status'] !== 'success') {
            return;
        }

        $this->scheduleConfirmation($targetId, 0);
    }

    public function confirmIndexing(int $targetId, int $attempt, bool $liveWatch = false): bool
    {
        $target = ArchiveOrgBackups::plugin()->getTargets()->getTargetById($targetId);

        if (!$target instanceof ArchiveTargetRecord || $target->lastSubmittedAt === null) {
            return false;
        }

        if (!$liveWatch && $attempt >= self::MAX_CONFIRMATION_ATTEMPTS) {
            ArchiveOrgBackups::plugin()->getTargets()->markIndexingFailed(
                $target,
                'Maximum Archive.org indexing confirmation attempts reached.'
            );

            return false;
        }

        try {
            $availability = $this->client->getAvailabilitySnapshot($target->url);
            $cdx = $this->client->getLatestCdxCapture($target->url);
        } catch (TemporaryArchiveOrgException) {
            if (!$liveWatch) {
                $this->scheduleConfirmation($targetId, $attempt + 1);
            }

            return false;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->updateIndexingResult(
                $target,
                false,
                null,
                null,
                $exception->getMessage()
            );

            return false;
        }

        $availabilityTimestamp = $availability['timestamp'] ?? null;
        $cdxTimestamp = $cdx['timestamp'] ?? null;
        $snapshotUrl = $availability['url'] ?? null;
        $indexed = self::isSnapshotCurrent(
            $target->lastSubmittedAt,
            $availabilityTimestamp,
            $cdxTimestamp
        );

        ArchiveOrgBackups::plugin()->getTargets()->updateIndexingResult(
            $target,
            $indexed,
            $availabilityTimestamp ?? $cdxTimestamp,
            $snapshotUrl ?? $target->lastSnapshotUrl,
            $indexed ? null : 'Archive.org has not exposed the snapshot via Availability/CDX yet.'
        );

        if (!$indexed && !$liveWatch) {
            $this->scheduleConfirmation($targetId, $attempt + 1);
        }

        return $indexed;
    }

    public function scheduleStatusPoll(int $targetId, int $attempt, int $delayAttempt): void
    {
        Craft::$app->getQueue()
            ->delay(ArchiveOrgBackups::plugin()->getScheduling()->getStatusPollDelay($delayAttempt))
            ->push(new \abromeit\archiveorgbackups\jobs\PollSubmissionStatusJob([
                'targetId' => $targetId,
                'attempt' => $attempt,
            ]));
    }

    public function scheduleConfirmation(int $targetId, int $attempt): void
    {
        Craft::$app->getQueue()
            ->delay(ArchiveOrgBackups::plugin()->getScheduling()->getConfirmationDelay($attempt))
            ->push(new ConfirmIndexingJob([
                'targetId' => $targetId,
                'attempt' => $attempt,
            ]));
    }
}
