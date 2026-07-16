<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%projects}}`.
 */
class m260301_024116_create_projects_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%projects}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'type' => $this->tinyInteger()->null(),
            'net_worth' => $this->decimal(15, 2)->null(),
            'roi' => $this->decimal(5, 2)->null(),
            'comment' => $this->string(5000)->null(),
            'status' => $this->tinyInteger()->null(),
            'employee_id' => $this->integer()->null(),
            'company_id' => $this->integer()->notNull()->defaultValue(0),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-projects-employee_id',
            '{{%projects}}',
            'employee_id',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-projects-created_by',
            '{{%projects}}',
            'created_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx-projects-status', '{{%projects}}', 'status');
        $this->createIndex('idx-projects-type', '{{%projects}}', 'type');
        $this->createIndex('idx-projects-employee_id', '{{%projects}}', 'employee_id');
        $this->createIndex('idx-projects-created_by', '{{%projects}}', 'created_by');
        $this->createIndex('idx-projects-company_id', '{{%projects}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-projects-employee_id', '{{%projects}}');
        $this->dropForeignKey('fk-projects-created_by', '{{%projects}}');
        $this->dropIndex('idx-projects-status', '{{%projects}}');
        $this->dropIndex('idx-projects-type', '{{%projects}}');
        $this->dropIndex('idx-projects-employee_id', '{{%projects}}');
        $this->dropIndex('idx-projects-created_by', '{{%projects}}');
        $this->dropIndex('idx-projects-company_id', '{{%projects}}');
        $this->dropTable('{{%projects}}');
    }
}
