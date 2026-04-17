<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\models;

use craft\base\Model;

final class Settings extends Model
{
    /**
     * @var string[]
     */
    public array $enabledSectionUids = [];

    public int $publicDailyLimit = 150;

    public int $changedResubmitHours = 24;

    public int $unchangedRefreshDays = 7;

    public int $heartbeatIntervalMinutes = 15;

    public function defineRules(): array
    {
        return [
            [['enabledSectionUids'], 'safe'],
            [['publicDailyLimit'], 'integer', 'min' => 1, 'max' => 10000],
            [['changedResubmitHours'], 'integer', 'min' => 1, 'max' => 168],
            [['unchangedRefreshDays'], 'integer', 'min' => 1, 'max' => 365],
            [['heartbeatIntervalMinutes'], 'integer', 'min' => 1, 'max' => 1440],
        ];
    }
}
