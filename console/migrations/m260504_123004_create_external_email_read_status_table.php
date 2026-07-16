<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%external_email_read_status}}`.
 */
class m260504_123004_create_external_email_read_status_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%external_email_read_status}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'is_read' => $this->boolean()->defaultValue(0),
            'is_flagged' => $this->boolean()->defaultValue(0),
            'read_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_external_email_read_status_message',
            '{{%external_email_read_status}}',
            'message_id',
            '{{%external_email_messages}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_external_email_read_status_user',
            '{{%external_email_read_status}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'unique_message_user',
            '{{%external_email_read_status}}',
            ['message_id', 'user_id'],
            true
        );

        $this->createIndex('idx_user_unread', '{{%external_email_read_status}}', ['user_id', 'is_read']);
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_external_email_read_status_message', '{{%external_email_read_status}}');
        $this->dropForeignKey('fk_external_email_read_status_user', '{{%external_email_read_status}}');
        $this->dropIndex('unique_message_user', '{{%external_email_read_status}}');
        $this->dropIndex('idx_user_unread', '{{%external_email_read_status}}');
        $this->dropTable('{{%external_email_read_status}}');
    }
}
