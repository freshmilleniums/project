<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%projects_documents}}`.
 */
class m260921_184915_create_projects_documents_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%projects_documents}}', [
            'id'         => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'path'       => $this->string(255)->notNull(),
        ]);

        $this->addForeignKey(
            'fk_projects_documents_project_id',
            '{{%projects_documents}}',
            'project_id',
            '{{%projects}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_projects_documents_project_id',
            '{{%projects_documents}}',
            'project_id'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_projects_documents_project_id', '{{%projects_documents}}');
        $this->dropTable('{{%projects_documents}}');
    }
}
