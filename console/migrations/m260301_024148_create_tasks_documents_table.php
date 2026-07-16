<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%tasks_documents}}`.
 */
class m260301_024148_create_tasks_documents_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%tasks_documents}}', [
            'id' => $this->primaryKey(),
            'task_id' => $this->integer()->notNull(),
            'path' => $this->string(255)->notNull(),
        ]);

        $this->addForeignKey(
            'fk-tasks_documents-task_id',
            '{{%tasks_documents}}',
            'task_id',
            '{{%tasks}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx-tasks_documents-task_id',
            '{{%tasks_documents}}',
            'task_id'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-tasks_documents-task_id', '{{%tasks_documents}}');
        $this->dropIndex('idx-tasks_documents-task_id', '{{%tasks_documents}}');
        $this->dropTable('{{%tasks_documents}}');
    }
}
