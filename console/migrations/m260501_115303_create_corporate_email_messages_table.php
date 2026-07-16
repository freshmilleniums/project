<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%corporate_email_messages}}`.
 */
class m260501_115303_create_corporate_email_messages_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%corporate_email_messages}}', [
            'id' => $this->primaryKey(),
            'corporate_email_id' => $this->integer()->notNull(),

            'message_id' => $this->string(255)->unique(),
            'message_uid' => $this->string(255)->null(),
            'thread_id' => $this->string(255)->null(),
            'in_reply_to' => $this->string(255)->null(),
            'references' => $this->text()->null(),

            'direction' => "ENUM('incoming', 'outgoing') NOT NULL",
            'folder' => $this->string(50)->defaultValue('INBOX'),

            'from_email' => $this->string(255)->notNull(),
            'from_name' => $this->string(255)->null(),
            'reply_to' => $this->string(255)->null(),
            'to_emails' => $this->text()->notNull(),
            'cc_emails' => $this->text()->null(),
            'bcc_emails' => $this->text()->null(),

            'subject' => $this->string(500)->null(),
            'body_text' => 'LONGTEXT NULL',
            'body_html' => 'LONGTEXT NULL',

            'is_read' => $this->boolean()->defaultValue(0),
            'is_draft' => $this->boolean()->defaultValue(0),
            'has_attachments' => $this->boolean()->defaultValue(0),

            'sent_at' => $this->integer()->unsigned()->null(),
            'received_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_corporate_email_messages_email',
            '{{%corporate_email_messages}}',
            'corporate_email_id',
            '{{%user_corporate_emails}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('unique_message_id', '{{%corporate_email_messages}}', 'message_id', true);
        $this->createIndex('idx_corporate_email_id', '{{%corporate_email_messages}}', 'corporate_email_id');
        $this->createIndex('idx_thread_id', '{{%corporate_email_messages}}', 'thread_id');
        $this->createIndex('idx_direction', '{{%corporate_email_messages}}', 'direction');
        $this->createIndex('idx_folder', '{{%corporate_email_messages}}', 'folder');
        $this->createIndex('idx_is_read', '{{%corporate_email_messages}}', 'is_read');
        $this->createIndex('idx_sent_at', '{{%corporate_email_messages}}', 'sent_at');
        $this->createIndex('idx_received_at', '{{%corporate_email_messages}}', 'received_at');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_corporate_email_messages_email', '{{%corporate_email_messages}}');
        $this->dropIndex('unique_message_id', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_corporate_email_id', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_thread_id', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_direction', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_folder', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_is_read', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_sent_at', '{{%corporate_email_messages}}');
        $this->dropIndex('idx_received_at', '{{%corporate_email_messages}}');
        $this->dropTable('{{%corporate_email_messages}}');
    }
}
