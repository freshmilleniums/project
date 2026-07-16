<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%tasks}}`.
 */
class m260301_024134_create_tasks_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%tasks}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'subject' => $this->string(255)->null(),
            'description' => $this->string(5000)->null(),
            'template_id' => $this->integer()->null(),
            'assigned_to' => $this->integer()->null(),
            'priority' => $this->tinyInteger()->null(),
            'status' => $this->tinyInteger()->null(),
            'company_id' => $this->integer()->notNull()->defaultValue(0),
            'due_date' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-tasks-assigned_to',
            '{{%tasks}}',
            'assigned_to',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-tasks-created_by',
            '{{%tasks}}',
            'created_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx-tasks-assigned_to', '{{%tasks}}', 'assigned_to');
        $this->createIndex('idx-tasks-status', '{{%tasks}}', 'status');
        $this->createIndex('idx-tasks-priority', '{{%tasks}}', 'priority');
        $this->createIndex('idx-tasks-due_date', '{{%tasks}}', 'due_date');
        $this->createIndex('idx-tasks-created_by', '{{%tasks}}', 'created_by');
        $this->createIndex('idx-tasks-company_id', '{{%tasks}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-tasks-assigned_to', '{{%tasks}}');
        $this->dropForeignKey('fk-tasks-created_by', '{{%tasks}}');

        $this->dropIndex('idx-tasks-assigned_to', '{{%tasks}}');
        $this->dropIndex('idx-tasks-status', '{{%tasks}}');
        $this->dropIndex('idx-tasks-priority', '{{%tasks}}');
        $this->dropIndex('idx-tasks-due_date', '{{%tasks}}');
        $this->dropIndex('idx-tasks-created_by', '{{%tasks}}');
        $this->dropIndex('idx-tasks-company_id', '{{%tasks}}');

        $this->dropTable('{{%tasks}}');
    }

}
