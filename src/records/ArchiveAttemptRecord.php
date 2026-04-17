<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\records;

use craft\db\ActiveRecord;

/**
 * @property int|null    $targetId
 * @property string      $type
 * @property string      $outcome
 * @property int|null    $remoteStatusCode
 * @property int|null    $observedDailyLimit
 * @property string|null $message
 * @property string|null $remotePayload
 */
final class ArchiveAttemptRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%archiveorgbackups_attempts}}';
    }
}
