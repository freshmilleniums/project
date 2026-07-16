<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%templates}}`.
 */
class m260322_103703_create_templates_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%templates}}', [
            'id' => $this->primaryKey(),
            'category' => $this->tinyInteger()->notNull(),
            'title' => $this->string(255)->notNull(),
            'subject' => $this->string(500)->null(),
            'body' => $this->text()->notNull(),
            'company_id' => $this->integer()->notNull()->defaultValue(0),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-templates-created_by',
            '{{%templates}}',
            'created_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx-templates-category', '{{%templates}}', 'category');
        $this->createIndex('idx-templates-title', '{{%templates}}', 'title');
        $this->createIndex('idx-templates-created_by', '{{%templates}}', 'created_by');
        $this->createIndex('idx-templates-company_id', '{{%templates}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-templates-created_by', '{{%templates}}');

        $this->dropIndex('idx-templates-category', '{{%templates}}');
        $this->dropIndex('idx-templates-title', '{{%templates}}');
        $this->dropIndex('idx-templates-created_by', '{{%templates}}');
        $this->dropIndex('idx-templates-company_id', '{{%templates}}');

        $this->dropTable('{{%templates}}');
    }
}
