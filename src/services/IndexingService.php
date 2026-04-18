<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use DateTimeImmutable;
use DateTimeZone;
use craft\base\Component;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\archiveorg\ArchiveOrgClientInterface;
use abromeit\archiveorgbackups\archiveorg\ArchiveOrgEndpoints;
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

    public static function isSnapshotCurrent(?string $submittedAt, ?string $cdxTimestamp): bool
    {
        $submitted = $submittedAt !== null ? strtotime($submittedAt) : false;
        $latest = self::normalizeTimestamp($cdxTimestamp);

        if ($submitted === false || $latest === 0) {
            return false;
        }

        return $latest >= ($submitted - 300);
    }

    public static function snapshotUrlFromCapture(?string $timestamp, ?string $original): ?string
    {
        if (!is_string($timestamp) || !is_string($original)) {
            return null;
        }

        if ($timestamp === '' || $original === '') {
            return null;
        }

        return ArchiveOrgEndpoints::snapshotUrl($timestamp, $original);
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

    public function pollSubmissionStatus(int $targetId, int $attempt, ?string $expectedJobId = null): void
    {
        $target = ArchiveOrgBackups::plugin()->getTargets()->getTargetById($targetId);

        if (!$target instanceof ArchiveTargetRecord || $target->lastJobId === null) {
            return;
        }

        if ($expectedJobId !== null && $target->lastJobId !== $expectedJobId) {
            return;
        }

        if ($attempt >= self::MAX_STATUS_POLL_ATTEMPTS) {
            ArchiveOrgBackups::plugin()->getTargets()->markSubmissionPollingFailed(
                $target,
                'Maximum Archive.org save-status polling attempts reached.'
            );

            return;
        }

        try {
            $status = $this->client->getSaveStatus($target->lastJobId);
        } catch (TemporaryArchiveOrgException) {
            $this->scheduleStatusPoll($targetId, $attempt + 1, $attempt + 1, $target->lastJobId);
            return;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->markSubmissionPollingFailed($target, $exception->getMessage());
            return;
        }

        if ($status['status'] === 'pending') {
            ArchiveOrgBackups::plugin()->getTargets()->updateStatusPoll(
                $target,
                $status['status'],
                $status['message'],
                $status['statusExt']
            );
            $this->scheduleStatusPoll($targetId, $attempt + 1, $attempt + 1, $target->lastJobId);
            return;
        }

        if ($status['status'] !== 'success') {
            $message = $status['statusExt'] ?? $status['message'];

            ArchiveOrgBackups::plugin()->getTargets()->markSubmissionPollingFailed($target, $message);
            return;
        }

        ArchiveOrgBackups::plugin()->getTargets()->updateStatusPoll(
            $target,
            $status['status'],
            $status['message'],
            $status['statusExt']
        );

        $this->scheduleConfirmation($targetId, 0, $target->lastJobId);
    }

    public function confirmIndexing(
        int $targetId,
        int $attempt,
        bool $liveWatch = false,
        ?string $expectedJobId = null
    ): bool
    {
        $target = ArchiveOrgBackups::plugin()->getTargets()->getTargetById($targetId);

        if (!$target instanceof ArchiveTargetRecord || $target->lastSubmittedAt === null) {
            return false;
        }

        if ($expectedJobId !== null && $target->lastJobId !== $expectedJobId) {
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
            $cdx = $this->client->getLatestCdxCapture($target->url);
        } catch (TemporaryArchiveOrgException) {
            if (!$liveWatch) {
                $this->scheduleConfirmation($targetId, $attempt + 1, $target->lastJobId);
            }

            return false;
        } catch (ArchiveOrgException $exception) {
            ArchiveOrgBackups::plugin()->getTargets()->markIndexingFailed($target, $exception->getMessage());

            return false;
        }

        $cdxTimestamp = $cdx['timestamp'] ?? null;
        $snapshotUrl = self::snapshotUrlFromCapture($cdxTimestamp, $cdx['original'] ?? null);
        $indexed = self::isSnapshotCurrent($target->lastSubmittedAt, $cdxTimestamp);

        ArchiveOrgBackups::plugin()->getTargets()->updateIndexingResult(
            $target,
            $indexed,
            $cdxTimestamp,
            $snapshotUrl ?? $target->lastSnapshotUrl,
            $indexed ? null : 'Archive.org has not exposed the snapshot via CDX yet.'
        );

        if (!$indexed && !$liveWatch) {
            $this->scheduleConfirmation($targetId, $attempt + 1, $target->lastJobId);
        }

        return $indexed;
    }

    public function scheduleStatusPoll(int $targetId, int $attempt, int $delayAttempt, string $expectedJobId): void
    {
        Craft::$app->getQueue()
            ->delay(ArchiveOrgBackups::plugin()->getScheduling()->getStatusPollDelay($delayAttempt))
            ->push(new \abromeit\archiveorgbackups\jobs\PollSubmissionStatusJob([
                'targetId' => $targetId,
                'attempt' => $attempt,
                'expectedJobId' => $expectedJobId,
            ]));
    }

    public function scheduleConfirmation(int $targetId, int $attempt, string $expectedJobId): void
    {
        Craft::$app->getQueue()
            ->delay(ArchiveOrgBackups::plugin()->getScheduling()->getConfirmationDelay($attempt))
            ->push(new ConfirmIndexingJob([
                'targetId' => $targetId,
                'attempt' => $attempt,
                'expectedJobId' => $expectedJobId,
            ]));
    }
}
