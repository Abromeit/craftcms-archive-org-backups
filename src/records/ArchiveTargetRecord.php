<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $elementId
 * @property int         $siteId
 * @property string      $url
 * @property string      $urlHash
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


    /**
     * Returns the 128-bit xxHash (hex, 32 chars) of the given URL.
     * Used for the unique (elementId, siteId, urlHash) index, because `url`
     * is stored as TEXT and cannot be part of an index directly on MySQL.
     *
     * @param  string $url - URL to hash.
     *
     * @return string
     */
    public static function hashUrl(string $url): string
    {
        return hash('xxh128', $url);
    }


    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->urlHash = self::hashUrl((string) $this->url);

        return true;
    }
}
