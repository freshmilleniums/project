<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_sent_templates}}`.
 */
class m260608_085108_create_user_sent_templates_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user_sent_templates}}', [
            'id'            => $this->primaryKey(),
            'user_id'       => $this->integer()->notNull(),
            'template_id'   => $this->integer()->null(),
            'sent_by'       => $this->integer()->null(),
            'template_name' => $this->string(255)->notNull(),
            'subject'       => $this->string(255)->null(),
            'body'          => $this->text()->null(),
            'sent_at'       => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-user_sent_templates-user_id',
            '{{%user_sent_templates}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            'fk-user_sent_templates-template_id',
            '{{%user_sent_templates}}', 'template_id',
            '{{%templates}}', 'id',
            'SET NULL', 'CASCADE'
        );

        $this->addForeignKey(
            'fk-user_sent_templates-sent_by',
            '{{%user_sent_templates}}', 'sent_by',
            '{{%user}}', 'id',
            'SET NULL', 'CASCADE'
        );

        $this->createIndex('idx-user_sent_templates-user_id', '{{%user_sent_templates}}', 'user_id');
        $this->createIndex('idx-user_sent_templates-template_id', '{{%user_sent_templates}}', 'template_id');
        $this->createIndex('idx-user_sent_templates-sent_by', '{{%user_sent_templates}}', 'sent_by');
        $this->createIndex('idx-user_sent_templates-sent_at', '{{%user_sent_templates}}', 'sent_at');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-user_sent_templates-sent_by', '{{%user_sent_templates}}');
        $this->dropForeignKey('fk-user_sent_templates-template_id', '{{%user_sent_templates}}');
        $this->dropForeignKey('fk-user_sent_templates-user_id', '{{%user_sent_templates}}');
        $this->dropIndex('idx-user_sent_templates-sent_at', '{{%user_sent_templates}}');
        $this->dropIndex('idx-user_sent_templates-sent_by', '{{%user_sent_templates}}');
        $this->dropIndex('idx-user_sent_templates-template_id', '{{%user_sent_templates}}');
        $this->dropIndex('idx-user_sent_templates-user_id', '{{%user_sent_templates}}');
        $this->dropTable('{{%user_sent_templates}}');
    }
}
