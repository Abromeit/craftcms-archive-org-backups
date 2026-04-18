<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Db;
use yii\helpers\Json;
use abromeit\archiveorgbackups\ArchiveOrgBackups;
use abromeit\archiveorgbackups\jobs\SubmitDueTargetsJob;
use abromeit\archiveorgbackups\jobs\SyncTargetsJob;
use abromeit\archiveorgbackups\records\ArchiveAttemptRecord;
use abromeit\archiveorgbackups\records\ArchiveTargetRecord;

final class TargetService extends Component
{
    private const DISCOVERY_BOOTSTRAP_CACHE_KEY = 'archive-org-backups:discovery-bootstrap';

    private const DISCOVERY_BOOTSTRAP_LOCK_KEY = 'archive-org-backups:discovery-bootstrap-lock';

    public function syncEntry(Entry $entry): void
    {
        $this->syncEntryId((int) $entry->id);
    }

    public function syncEntryId(int $entryId): void
    {
        $manifest = ArchiveOrgBackups::plugin()->getManifest()->getEntryManifestById($entryId);
        $seenKeys = [];

        foreach ($manifest as $row) {
            $this->upsertTarget($row);
            $seenKeys[] = $this->rowKey((int) $row['siteId'], (string) $row['url']);
        }

        /** @var ArchiveTargetRecord[] $existing */
        $existing = ArchiveTargetRecord::findAll(['elementId' => $entryId]);

        foreach ($existing as $record) {
            if (in_array($this->rowKey((int) $record->siteId, (string) $record->url), $seenKeys, true)) {
                continue;
            }

            $this->retireTarget($record);
        }
    }

    public function retireEntry(int $entryId): void
    {
        /** @var ArchiveTargetRecord[] $rows */
        $rows = ArchiveTargetRecord::findAll(['elementId' => $entryId]);

        foreach ($rows as $row) {
            $this->retireTarget($row);
        }
    }

    public function retireInvalidTargets(): void
    {
        /** @var ArchiveTargetRecord[] $rows */
        $rows = ArchiveTargetRecord::find()
            ->where(['isActive' => true])
            ->all();

        foreach ($rows as $row) {
            $entry = Craft::$app->getElements()->getElementById(
                (int) $row->elementId,
                Entry::class,
                (int) $row->siteId
            );

            if (!$entry instanceof Entry) {
                $this->retireTarget($row);
                continue;
            }

            if (!ArchiveOrgBackups::plugin()->getManifest()->isTrackableEntry($entry)) {
                $this->retireTarget($row);
            }
        }
    }

    public function syncManifestBatch(int $offset, int $limit): int
    {
        $entryIds = ArchiveOrgBackups::plugin()->getManifest()->getTrackedEntryIds($offset, $limit);

        foreach ($entryIds as $entryId) {
            $this->syncEntryId($entryId);
        }

        return count($entryIds);
    }

    public function primeManifest(int $limit): int
    {
        $entryIds = ArchiveOrgBackups::plugin()->getManifest()->getTrackedEntryIds(0, $limit);

        foreach ($entryIds as $entryId) {
            $this->syncEntryId($entryId);
        }

        return count($entryIds);
    }

    /**
     * @return ArchiveTargetRecord[]
     */
    public function getDueTargets(int $limit): array
    {
        $now = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        /** @var ArchiveTargetRecord[] $rows */
        $rows = ArchiveTargetRecord::find()
            ->where(['isActive' => true])
            ->andWhere(['not', ['nextSubmissionAt' => null]])
            ->andWhere(['<=', 'nextSubmissionAt', $now])
            ->andWhere([
                'or',
                ['lastJobStatus' => null],
                ['<>', 'lastJobStatus', ArchiveOrgBackups::JOB_STATUS_PENDING],
            ])
            ->orderBy([
                'priority' => SORT_DESC,
                'nextSubmissionAt' => SORT_ASC,
                'id' => SORT_ASC,
            ])
            ->limit($limit)
            ->all();

        return $rows;
    }

    public function getTargetById(int $targetId): ?ArchiveTargetRecord
    {
        /** @var ArchiveTargetRecord|null */
        return ArchiveTargetRecord::findOne($targetId);
    }

    /**
     * @param int[] $ids
     * @return array<int, array{id:int, lastJobId:?string, lastRemoteCheckAt:?string}>
     */
    public function getPendingLiveCandidates(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, array{id:int, lastJobId:?string, lastRemoteCheckAt:?string}> $rows */
        $rows = (new Query())
            ->select(['id', 'lastJobId', 'lastRemoteCheckAt'])
            ->from(ArchiveTargetRecord::tableName())
            ->where(['id' => $ids, 'isActive' => true])
            ->andWhere(['indexingStatus' => ArchiveOrgBackups::INDEXING_PENDING])
            ->andWhere(['lastJobStatus' => ArchiveOrgBackups::JOB_STATUS_SUCCESS])
            ->all();

        return $rows;
    }

    public function updateSubmissionAccepted(
        ArchiveTargetRecord $record,
        string $jobId,
        ?int $observedLimit = null
    ): void {
        $record->lastSubmittedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $record->lastSubmittedSourceDateUpdated = $record->sourceDateUpdated;
        $record->lastJobId = $jobId;
        $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_PENDING;
        $record->indexingStatus = ArchiveOrgBackups::INDEXING_PENDING;
        $record->indexedAt = null;
        $record->lastSnapshotTimestamp = null;
        $record->lastSnapshotUrl = null;
        $record->lastError = null;
        $record->nextSubmissionAt = null;
        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'submit',
            'accepted',
            null,
            200,
            $observedLimit,
            Json::encode([
                'jobId' => $jobId,
                'url' => $record->url,
            ])
        );
    }

    public function updateSubmissionFailure(
        ArchiveTargetRecord $record,
        string $message,
        string $outcome,
        ?string $nextSubmissionAt = null,
        ?int $observedLimit = null
    ): void {
        if ($outcome === 'quota_exhausted') {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_QUOTA_EXHAUSTED;
        } elseif ($outcome === 'retry') {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_RETRY;
        } else {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_FAILED;
        }

        $record->lastError = $message;
        $record->nextSubmissionAt = $nextSubmissionAt;
        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'submit',
            $outcome,
            $message,
            null,
            $observedLimit,
            null
        );
    }

    public function updateStatusPoll(
        ArchiveTargetRecord $record,
        string $status,
        string $message = '',
        ?string $statusExt = null
    ): void {
        if ($status === 'success') {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_SUCCESS;
            $record->lastError = null;
        } elseif ($status === 'pending') {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_PENDING;
        } else {
            $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_FAILED;
            $record->lastError = $statusExt ?? $message;
        }

        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'status_poll',
            $status,
            $message,
            null,
            null,
            Json::encode(['statusExt' => $statusExt])
        );
    }

    public function updateIndexingResult(
        ArchiveTargetRecord $record,
        bool $indexed,
        ?string $timestamp,
        ?string $snapshotUrl,
        ?string $message = null
    ): void {
        $record->lastRemoteCheckAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        if ($indexed) {
            $record->indexingStatus = ArchiveOrgBackups::INDEXING_INDEXED;
            $record->indexedAt = $record->lastRemoteCheckAt;
            $record->lastSnapshotTimestamp = $timestamp;
            $record->lastSnapshotUrl = $snapshotUrl;
            $record->lastError = null;
            $record->priority = SchedulingService::calculatePriority(
                $record->lastSubmittedSourceDateUpdated,
                $record->sourceDateUpdated
            );
            $record->nextSubmissionAt = Db::prepareDateForDb(
                SchedulingService::calculateNextSubmissionAt(
                    $record->lastSubmittedAt,
                    $record->lastSubmittedSourceDateUpdated,
                    $record->sourceDateUpdated,
                    ArchiveOrgBackups::plugin()->getScheduling()->getUnchangedRefreshDays(),
                    ArchiveOrgBackups::plugin()->getScheduling()->getChangedTargetWindowHours()
                )
            );
        } else {
            $record->indexingStatus = ArchiveOrgBackups::INDEXING_PENDING;
            $record->lastError = $message;
        }

        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'index_check',
            $indexed ? 'indexed' : 'pending',
            $message,
            null,
            null,
            Json::encode([
                'timestamp' => $timestamp,
                'snapshotUrl' => $snapshotUrl,
            ])
        );
    }

    public function markIndexingFailed(ArchiveTargetRecord $record, string $message): void
    {
        $record->indexingStatus = ArchiveOrgBackups::INDEXING_FAILED;
        $record->lastError = $message;
        $record->nextSubmissionAt = Db::prepareDateForDb(
            ArchiveOrgBackups::plugin()->getScheduling()->getRetrySubmissionAt()
        );
        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'index_check',
            'failed',
            $message,
            null,
            null,
            null
        );
    }

    public function addAttempt(
        ?int $targetId,
        string $type,
        string $outcome,
        ?string $message,
        ?int $remoteStatusCode,
        ?int $observedDailyLimit,
        ?string $remotePayload
    ): void {
        $attempt = new ArchiveAttemptRecord();
        $attempt->targetId = $targetId;
        $attempt->type = $type;
        $attempt->outcome = $outcome;
        $attempt->message = $message;
        $attempt->remoteStatusCode = $remoteStatusCode;
        $attempt->observedDailyLimit = $observedDailyLimit;
        $attempt->remotePayload = $remotePayload;
        $attempt->save(false);
    }

    /**
     * @return array{rows:array<int, array<string, mixed>>, notice:string}
     */
    public function getDashboardRows(string $sort, string $dir): array
    {
        $this->bootstrapDiscoveryIfNeeded();

        $allowedSorts = ['url', 'lastSubmittedAt', 'nextSubmissionAt'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'nextSubmissionAt';
        $dir = strtolower($dir) === 'desc' ? SORT_DESC : SORT_ASC;
        $orderBy = [$sort => $dir];

        if ($sort !== 'url') {
            $orderBy['url'] = SORT_ASC;
        }

        $rows = ArchiveTargetRecord::find()
            ->where(['isActive' => true])
            ->orderBy($orderBy)
            ->all();
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row->id,
                'url' => $row->url,
                'lastSubmissionLabel' => $row->lastSubmittedAt
                    ? Craft::$app->getFormatter()->asDatetime($row->lastSubmittedAt)
                    : null,
                'lastSnapshotUrl' => $row->lastSnapshotUrl,
                'nextSubmissionLabel' => $row->nextSubmissionAt
                    ? Craft::$app->getFormatter()->asDatetime($row->nextSubmissionAt)
                    : null,
                'statusLabel' => $this->statusLabel($row),
                'hasSnapshotUrl' => $row->lastSnapshotUrl !== null && $row->lastSnapshotUrl !== '',
                'isIndexed' => $row->indexingStatus === ArchiveOrgBackups::INDEXING_INDEXED,
            ];
        }

        $notice = '';

        if ($rows === []) {
            $notice = ArchiveOrgBackups::plugin()->getManifest()->getEnabledSectionIds() === []
                ? Craft::t(
                    ArchiveOrgBackups::TRANSLATION_CATEGORY,
                    'Enable at least one entry section to start tracking archive targets.'
                )
                : Craft::t(
                    ArchiveOrgBackups::TRANSLATION_CATEGORY,
                    'Archive targets are being discovered. If this does not change, run the queue worker.'
                );
        } elseif (ArchiveOrgBackups::plugin()->getQuota()->isQuotaExhausted()) {
            $notice = Craft::t(
                ArchiveOrgBackups::TRANSLATION_CATEGORY,
                'The public daily submission budget is currently exhausted.'
            );
        }

        return [
            'rows' => $result,
            'notice' => $notice,
        ];
    }

    private function bootstrapDiscoveryIfNeeded(): void
    {
        if (ArchiveOrgBackups::plugin()->getManifest()->getEnabledSectionIds() === []) {
            return;
        }

        if (ArchiveTargetRecord::find()->where(['isActive' => true])->exists()) {
            return;
        }

        $cache = Craft::$app->getCache();
        $lastBootstrapAt = (int) ($cache->get(self::DISCOVERY_BOOTSTRAP_CACHE_KEY) ?: 0);

        if (($lastBootstrapAt + 300) > time()) {
            return;
        }

        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::DISCOVERY_BOOTSTRAP_LOCK_KEY, 0)) {
            return;
        }

        try {
            if (ArchiveTargetRecord::find()->where(['isActive' => true])->exists()) {
                return;
            }

            $cache->set(self::DISCOVERY_BOOTSTRAP_CACHE_KEY, time(), 600);
            $this->primeManifest(100);

            if (!ArchiveTargetRecord::find()->where(['isActive' => true])->exists()) {
                return;
            }

            Craft::$app->getQueue()->push(new SyncTargetsJob());
            Craft::$app->getQueue()->push(new SubmitDueTargetsJob());
            ArchiveOrgBackups::plugin()->getHeartbeat()->ensureScheduled();
        } finally {
            $mutex->release(self::DISCOVERY_BOOTSTRAP_LOCK_KEY);
        }
    }

    /**
     * @param array{elementId:int, siteId:int, url:string, sourceDateUpdated:string} $data
     */
    private function upsertTarget(array $data): void
    {
        /** @var ArchiveTargetRecord|null $record */
        $record = ArchiveTargetRecord::findOne([
            'elementId' => $data['elementId'],
            'siteId' => $data['siteId'],
            'urlHash' => ArchiveTargetRecord::hashUrl((string) $data['url']),
        ]);

        if (!$record instanceof ArchiveTargetRecord) {
            $record = new ArchiveTargetRecord();
            $record->elementId = $data['elementId'];
            $record->siteId = $data['siteId'];
            $record->url = $data['url'];
        }

        $record->isActive = true;
        $record->sourceDateUpdated = $data['sourceDateUpdated'];
        $record->priority = SchedulingService::calculatePriority(
            $record->lastSubmittedSourceDateUpdated,
            $record->sourceDateUpdated
        );

        if ($record->lastJobStatus !== ArchiveOrgBackups::JOB_STATUS_PENDING) {
            $record->nextSubmissionAt = Db::prepareDateForDb(
                SchedulingService::calculateNextSubmissionAt(
                    $record->lastSubmittedAt,
                    $record->lastSubmittedSourceDateUpdated,
                    $record->sourceDateUpdated,
                    ArchiveOrgBackups::plugin()->getScheduling()->getUnchangedRefreshDays(),
                    ArchiveOrgBackups::plugin()->getScheduling()->getChangedTargetWindowHours()
                )
            );
        }

        $record->save(false);
    }

    private function retireTarget(ArchiveTargetRecord $record): void
    {
        $record->isActive = false;
        $record->nextSubmissionAt = null;
        $record->save(false);
    }

    public function markSubmissionPollingFailed(ArchiveTargetRecord $record, string $message): void
    {
        $record->lastJobStatus = ArchiveOrgBackups::JOB_STATUS_FAILED;
        $record->indexingStatus = ArchiveOrgBackups::INDEXING_FAILED;
        $record->lastError = $message;
        $record->nextSubmissionAt = Db::prepareDateForDb(
            ArchiveOrgBackups::plugin()->getScheduling()->getRetrySubmissionAt()
        );
        $record->save(false);

        $this->addAttempt(
            (int) $record->id,
            'status_poll',
            'failed',
            $message,
            null,
            null,
            null
        );
    }

    private function rowKey(int $siteId, string $url): string
    {
        return $siteId . ':' . $url;
    }

    public static function statusLabelKey(?string $lastJobStatus, string $indexingStatus): string
    {
        if ($lastJobStatus === ArchiveOrgBackups::JOB_STATUS_PENDING) {
            return 'Submitted';
        }

        if ($lastJobStatus === ArchiveOrgBackups::JOB_STATUS_QUOTA_EXHAUSTED) {
            return 'Daily limit reached';
        }

        if ($lastJobStatus === ArchiveOrgBackups::JOB_STATUS_RETRY) {
            return 'Retry scheduled';
        }

        if ($lastJobStatus === ArchiveOrgBackups::JOB_STATUS_FAILED) {
            return 'Error';
        }

        if ($indexingStatus === ArchiveOrgBackups::INDEXING_FAILED) {
            return 'Indexing failed';
        }

        if ($indexingStatus === ArchiveOrgBackups::INDEXING_INDEXED) {
            return 'Successfully archived';
        }

        if (
            $lastJobStatus === ArchiveOrgBackups::JOB_STATUS_SUCCESS
            || $indexingStatus === ArchiveOrgBackups::INDEXING_PENDING
        ) {
            return 'Submitted';
        }

        return 'Awaiting submission';
    }

    private function statusLabel(ArchiveTargetRecord $row): string
    {
        return Craft::t(
            ArchiveOrgBackups::TRANSLATION_CATEGORY,
            self::statusLabelKey(
                $row->lastJobStatus,
                $row->indexingStatus
            )
        );
    }
}
