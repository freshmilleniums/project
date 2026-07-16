<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%external_email_messages}}`.
 */
class m260504_121725_create_external_email_messages_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%external_email_messages}}', [
            'id' => $this->primaryKey(),
            'email_account_id' => $this->integer()->notNull(),

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

            'is_draft' => $this->boolean()->defaultValue(0),
            'has_attachments' => $this->boolean()->defaultValue(0),

            'sent_at' => $this->integer()->unsigned()->null(),
            'received_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_external_email_messages_account',
            '{{%external_email_messages}}',
            'email_account_id',
            '{{%email_accounts}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('unique_message_id', '{{%external_email_messages}}', 'message_id', true);
        $this->createIndex('idx_email_account_id', '{{%external_email_messages}}', 'email_account_id');
        $this->createIndex('idx_thread_id', '{{%external_email_messages}}', 'thread_id');
        $this->createIndex('idx_direction', '{{%external_email_messages}}', 'direction');
        $this->createIndex('idx_folder', '{{%external_email_messages}}', 'folder');
        $this->createIndex('idx_sent_at', '{{%external_email_messages}}', 'sent_at');
        $this->createIndex('idx_received_at', '{{%external_email_messages}}', 'received_at');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_external_email_messages_account', '{{%external_email_messages}}');
        $this->dropIndex('unique_message_id', '{{%external_email_messages}}');
        $this->dropIndex('idx_email_account_id', '{{%external_email_messages}}');
        $this->dropIndex('idx_thread_id', '{{%external_email_messages}}');
        $this->dropIndex('idx_direction', '{{%external_email_messages}}');
        $this->dropIndex('idx_folder', '{{%external_email_messages}}');
        $this->dropIndex('idx_sent_at', '{{%external_email_messages}}');
        $this->dropIndex('idx_received_at', '{{%external_email_messages}}');
        $this->dropTable('{{%external_email_messages}}');
    }
}
