<?php

use yii\db\Migration;

class m250725_203139_create_reminders_users_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%reminders_users}}', [
            'id' => $this->primaryKey(),
            'reminder_id' => $this->integer()->notNull(),
            'reminder_code' => $this->string(255)->notNull()->unique(),
            'user_id' => $this->integer()->notNull(),
            'text' => $this->text(),
            'read' => $this->integer(1)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);

        // Индексы
        $this->createIndex(
            'idx-reminders_users-reminder_id',
            '{{%reminders_users}}',
            'reminder_id'
        );

        $this->createIndex(
            'idx-reminders_users-reminder_code',
            '{{%reminders_users}}',
            'reminder_code'
        );

        $this->createIndex(
            'idx-reminders_users-user_id',
            '{{%reminders_users}}',
            'user_id'
        );

        $this->createIndex(
            'idx-reminders_users-read',
            '{{%reminders_users}}',
            'read'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%reminders_users}}');
    }
}
