<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * Adds the `urlHash` column and the corresponding unique index to
 * {{%archiveorgbackups_targets}}. Required because the original Install
 * migration tried to create a unique index on a TEXT column, which MySQL
 * rejects. On broken installations the table already exists without this
 * column, so this migration brings the schema in line with a fresh install.
 */
final class m260417_222400_add_url_hash_to_targets extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%archiveorgbackups_targets}}';
        $db = Craft::$app->getDb();

        if (!$db->tableExists($table)) {
            return true;
        }

        if (!$db->columnExists($table, 'urlHash')) {
            $this->addColumn(
                $table,
                'urlHash',
                $this->char(32)->notNull()->defaultValue('')->after('url')
            );
        }

        $rows = (new Query())
            ->select(['id', 'url'])
            ->from($table)
            ->where(['urlHash' => ''])
            ->all();

        foreach ($rows as $row) {
            $this->update(
                $table,
                ['urlHash' => hash('xxh128', (string) $row['url'])],
                ['id' => $row['id']],
                [],
                false
            );
        }

        $this->alterColumn($table, 'urlHash', $this->char(32)->notNull());

        MigrationHelper::dropIndexIfExists($table, ['elementId', 'siteId', 'url'], true, $this);
        MigrationHelper::dropIndexIfExists($table, ['elementId', 'siteId', 'urlHash'], true, $this);

        $this->createIndex(null, $table, ['elementId', 'siteId', 'urlHash'], true);

        return true;
    }


    public function safeDown(): bool
    {
        $table = '{{%archiveorgbackups_targets}}';
        $db = Craft::$app->getDb();

        MigrationHelper::dropIndexIfExists($table, ['elementId', 'siteId', 'urlHash'], true, $this);

        if ($db->columnExists($table, 'urlHash')) {
            $this->dropColumn($table, 'urlHash');
        }

        return true;
    }
}
