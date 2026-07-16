<?php

use yii\db\Migration;

class m250827_175159_add_chat_enhancements extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%chat_messages}}', 'reply_to_message_id', $this->integer()->null()->after('message_type'));

        $this->addForeignKey(
            'fk-chat_messages-reply_to_message_id',
            '{{%chat_messages}}',
            'reply_to_message_id',
            '{{%chat_messages}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex(
            'idx-chat_messages-reply_to_message_id',
            '{{%chat_messages}}',
            'reply_to_message_id'
        );

        $this->createTable('{{%chat_message_attachments}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'original_name' => $this->string(255)->notNull(),
            'stored_name' => $this->string(255)->notNull(),
            'file_path' => $this->string(500)->notNull(),
            'file_size' => $this->integer()->notNull(),
            'file_type' => $this->string(100)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-chat_message_attachments-message_id',
            '{{%chat_message_attachments}}',
            'message_id',
            '{{%chat_messages}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx-chat_message_attachments-message_id',
            '{{%chat_message_attachments}}',
            'message_id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-chat_messages-reply_to_message_id', '{{%chat_messages}}');
        $this->dropIndex('idx-chat_messages-reply_to_message_id', '{{%chat_messages}}');

        $this->dropColumn('{{%chat_messages}}', 'reply_to_message_id');

        $this->dropTable('{{%chat_message_attachments}}');
    }
}