<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%notification}}`.
 */
class m250730_002904_create_notification_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%notification}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'text' => $this->string(500)->notNull(),
            'read' => $this->boolean()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'resent_at' => $this->integer()->null(),
            'resent_by' => $this->integer()->null(),
        ]);

        $this->addForeignKey(
            'fk-notification-user_id',
            '{{%notification}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-notification-resent_by',
            '{{%notification}}',
            'resent_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-notification-user_id', '{{%notification}}');
        $this->dropForeignKey('fk-notification-resent_by', '{{%notification}}');
        $this->dropTable('{{%notification}}');
    }
}