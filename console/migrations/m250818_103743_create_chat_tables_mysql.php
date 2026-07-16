<?php

use yii\db\Migration;

class m250818_103743_create_chat_tables_mysql extends Migration
{
    public function safeUp()
    {
        $this->createTable('chats', [
            'id' => $this->string(36)->notNull()->append('PRIMARY KEY'),
            'type' => "ENUM('employee', 'user_private', 'user_group') NOT NULL",
            'title' => $this->string()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'last_message_at' => $this->integer()->null(),
            'employee_id' => $this->integer()->null(),
        ]);

        $this->addForeignKey('fk_chats_employee', 'chats', 'employee_id', 'user', 'id', 'SET NULL', 'CASCADE');
        $this->createIndex('idx_chats_type', 'chats', 'type');
        $this->createIndex('idx_chats_employee_id', 'chats', 'employee_id');
        $this->createIndex('idx_chats_last_message_at', 'chats', 'last_message_at');

        $this->createTable('chat_participants', [
            'id' => $this->primaryKey(),
            'chat_id' => $this->string(36)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'joined_at' => $this->integer()->notNull(),
            'left_at' => $this->integer()->null(),
        ]);

        $this->addForeignKey('fk_chat_participants_chat', 'chat_participants', 'chat_id', 'chats', 'id', 'CASCADE');
        $this->addForeignKey('fk_chat_participants_user', 'chat_participants', 'user_id', 'user', 'id', 'CASCADE');
        $this->createIndex('idx_chat_participants_user_id', 'chat_participants', 'user_id');
        $this->createIndex('idx_chat_participants_chat_id', 'chat_participants', 'chat_id');
        $this->createIndex('idx_chat_participants_unique', 'chat_participants', ['chat_id', 'user_id'], true);

        $this->createTable('chat_messages', [
            'id' => $this->primaryKey(),
            'chat_id' => $this->string(36)->notNull(),
            'sender_id' => $this->integer()->notNull(),
            'message_text' => $this->text()->notNull(),
            'message_type' => $this->tinyInteger()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->null(),
            'is_deleted' => $this->tinyInteger(1)->defaultValue(0),
        ]);

        $this->addForeignKey('fk_chat_messages_chat', 'chat_messages', 'chat_id', 'chats', 'id', 'CASCADE');
        $this->addForeignKey('fk_chat_messages_sender', 'chat_messages', 'sender_id', 'user', 'id', 'CASCADE');
        $this->createIndex('idx_chat_messages_chat_id', 'chat_messages', 'chat_id');
        $this->createIndex('idx_chat_messages_created_at', 'chat_messages', 'created_at');

        $this->createTable('chat_message_read_status', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'is_read' => $this->tinyInteger(1)->defaultValue(0),
            'read_at' => $this->integer()->null(),
        ]);

        $this->addForeignKey('fk_chat_message_read_status_message', 'chat_message_read_status', 'message_id', 'chat_messages', 'id', 'CASCADE');
        $this->addForeignKey('fk_chat_message_read_status_user', 'chat_message_read_status', 'user_id', 'user', 'id', 'CASCADE');
        $this->createIndex('idx_chat_message_read_status_user_message', 'chat_message_read_status', ['user_id', 'message_id'], true);
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_chat_message_read_status_user', 'chat_message_read_status');
        $this->dropForeignKey('fk_chat_message_read_status_message', 'chat_message_read_status');
        $this->dropForeignKey('fk_chat_messages_sender', 'chat_messages');
        $this->dropForeignKey('fk_chat_messages_chat', 'chat_messages');
        $this->dropForeignKey('fk_chat_participants_user', 'chat_participants');
        $this->dropForeignKey('fk_chat_participants_chat', 'chat_participants');
        $this->dropForeignKey('fk_chats_employee', 'chats');

        $this->dropTable('chat_message_read_status');
        $this->dropTable('chat_messages');
        $this->dropTable('chat_participants');
        $this->dropTable('chats');
    }
}