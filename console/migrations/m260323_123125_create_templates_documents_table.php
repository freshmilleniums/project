<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%templates_documents}}`.
 */
class m260323_123125_create_templates_documents_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%templates_documents}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'path' => $this->string(255)->notNull(),
        ]);

        $this->addForeignKey(
            'fk-templates_documents-template_id',
            '{{%templates_documents}}',
            'template_id',
            '{{%templates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx-templates_documents-template_id',
            '{{%templates_documents}}',
            'template_id'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-templates_documents-template_id', '{{%templates_documents}}');
        $this->dropIndex('idx-templates_documents-template_id', '{{%templates_documents}}');
        $this->dropTable('{{%templates_documents}}');
    }
}
