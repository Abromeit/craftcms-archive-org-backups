<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\migrations;

use craft\db\Migration;

final class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%archiveorgbackups_targets}}')) {
            $this->createTable('{{%archiveorgbackups_targets}}', [
                'id' => $this->primaryKey(),
                'elementId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'url' => $this->text()->notNull(),
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'sourceDateUpdated' => $this->dateTime()->notNull(),
                'lastSubmittedAt' => $this->dateTime(),
                'lastSubmittedSourceDateUpdated' => $this->dateTime(),
                'lastSnapshotTimestamp' => $this->string(14),
                'lastSnapshotUrl' => $this->text(),
                'nextSubmissionAt' => $this->dateTime(),
                'lastJobId' => $this->string(255),
                'lastJobStatus' => $this->string(32),
                'indexingStatus' => $this->string(32)->notNull()->defaultValue('unknown'),
                'indexedAt' => $this->dateTime(),
                'lastRemoteCheckAt' => $this->dateTime(),
                'lastError' => $this->text(),
                'priority' => $this->smallInteger()->notNull()->defaultValue(100),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(
                null,
                '{{%archiveorgbackups_targets}}',
                ['elementId', 'siteId', 'url'],
                true
            );
            $this->createIndex(
                null,
                '{{%archiveorgbackups_targets}}',
                ['isActive', 'nextSubmissionAt', 'priority'],
                false
            );
            $this->createIndex(
                null,
                '{{%archiveorgbackups_targets}}',
                ['lastJobStatus', 'indexingStatus'],
                false
            );
        }

        if (!$this->db->tableExists('{{%archiveorgbackups_attempts}}')) {
            $this->createTable('{{%archiveorgbackups_attempts}}', [
                'id' => $this->primaryKey(),
                'targetId' => $this->integer(),
                'type' => $this->string(32)->notNull(),
                'outcome' => $this->string(32)->notNull(),
                'remoteStatusCode' => $this->integer(),
                'observedDailyLimit' => $this->integer(),
                'message' => $this->text(),
                'remotePayload' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%archiveorgbackups_attempts}}', ['targetId', 'type'], false);
            $this->createIndex(null, '{{%archiveorgbackups_attempts}}', ['type', 'outcome', 'dateCreated'], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%archiveorgbackups_attempts}}');
        $this->dropTableIfExists('{{%archiveorgbackups_targets}}');

        return true;
    }
}
