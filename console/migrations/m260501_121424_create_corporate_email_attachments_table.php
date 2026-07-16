<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%corporate_email_attachments}}`.
 */
class m260501_121424_create_corporate_email_attachments_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%corporate_email_attachments}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'filename' => $this->string(255)->notNull(),
            'content_type' => $this->string(100)->null(),
            'size' => $this->integer()->unsigned()->defaultValue(0),
            'file_path' => $this->string(500)->notNull(),
            'created_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_corporate_email_attachments_message',
            '{{%corporate_email_attachments}}',
            'message_id',
            '{{%corporate_email_messages}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('idx_message_id', '{{%corporate_email_attachments}}', 'message_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_corporate_email_attachments_message', '{{%corporate_email_attachments}}');
        $this->dropIndex('idx_message_id', '{{%corporate_email_attachments}}');
        $this->dropTable('{{%corporate_email_attachments}}');
    }
}
