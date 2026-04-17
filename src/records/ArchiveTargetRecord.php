<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $elementId
 * @property int         $siteId
 * @property string      $url
 * @property bool        $isActive
 * @property string      $sourceDateUpdated
 * @property string|null $lastSubmittedAt
 * @property string|null $lastSubmittedSourceDateUpdated
 * @property string|null $lastSnapshotTimestamp
 * @property string|null $lastSnapshotUrl
 * @property string|null $nextSubmissionAt
 * @property string|null $lastJobId
 * @property string|null $lastJobStatus
 * @property string      $indexingStatus
 * @property string|null $indexedAt
 * @property string|null $lastRemoteCheckAt
 * @property string|null $lastError
 * @property int         $priority
 */
final class ArchiveTargetRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%archiveorgbackups_targets}}';
    }
}
