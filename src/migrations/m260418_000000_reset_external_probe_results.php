<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\migrations;

use Craft;
use craft\db\Migration;

/**
 * Resets results from the initial external-probe backfill. Those results were
 * written by an earlier version of the CDX client that requested the oldest
 * capture in the lookup window instead of the newest one. Rows where we have
 * not submitted ourselves are cleared so the probe picks them up again and
 * stores the correct, most recent snapshot timestamp.
 */
final class m260418_000000_reset_external_probe_results extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%archiveorgbackups_targets}}';
        $db = Craft::$app->getDb();

        if (!$db->tableExists($table)) {
            return true;
        }

        $this->update(
            $table,
            [
                'lastSnapshotTimestamp' => null,
                'lastSnapshotUrl' => null,
                'lastRemoteCheckAt' => null,
            ],
            [
                'and',
                ['lastSubmittedAt' => null],
                ['not', ['lastRemoteCheckAt' => null]],
            ],
            [],
            false
        );

        return true;
    }


    public function safeDown(): bool
    {
        return true;
    }
}
