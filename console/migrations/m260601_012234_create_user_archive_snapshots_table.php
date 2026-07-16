<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_archive_snapshots}}`.
 */
class m260601_012234_create_user_archive_snapshots_table extends Migration
{
    public function up()
    {
        $this->createTable('user_archive_snapshots', [
            'id'            => $this->primaryKey(),
            'user_id'       => $this->integer()->notNull(),
            'snapshot_data' => $this->text()->notNull(),
            'archived_by'   => $this->integer()->notNull(),
            'archived_at'   => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_archive_snapshot_user_id',
            'user_archive_snapshots',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_archive_snapshot_archived_by',
            'user_archive_snapshots',
            'archived_by',
            'user',
            'id',
            'RESTRICT'
        );

        $this->createIndex(
            'idx_archive_snapshot_user_id',
            'user_archive_snapshots',
            'user_id'
        );
    }

    public function down()
    {
        $this->dropForeignKey('fk_archive_snapshot_archived_by', 'user_archive_snapshots');
        $this->dropForeignKey('fk_archive_snapshot_user_id', 'user_archive_snapshots');
        $this->dropTable('user_archive_snapshots');
    }
}
