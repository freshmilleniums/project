<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%external_email_attachments}}`.
 */
class m260504_122803_create_external_email_attachments_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%external_email_attachments}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'filename' => $this->string(255)->notNull(),
            'content_type' => $this->string(100)->null(),
            'size' => $this->integer()->unsigned()->defaultValue(0),
            'file_path' => $this->string(500)->notNull(),
            'created_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_external_email_attachments_message',
            '{{%external_email_attachments}}',
            'message_id',
            '{{%external_email_messages}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('idx_message_id', '{{%external_email_attachments}}', 'message_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_external_email_attachments_message', '{{%external_email_attachments}}');
        $this->dropIndex('idx_message_id', '{{%external_email_attachments}}');
        $this->dropTable('{{%external_email_attachments}}');
    }
}
