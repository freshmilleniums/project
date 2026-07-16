<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%investor_employee}}`.
 */
class m260318_201939_create_investor_employee_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%investor_employee}}', [
            'id' => $this->primaryKey(),
            'investor_id' => $this->integer()->notNull(),
            'employee_id' => $this->integer()->notNull(),
            'assigned_by' => $this->integer()->null(),
            'assigned_at' => $this->integer()->null(),
        ]);

        $this->addForeignKey(
            'fk-investor_employee-investor_id',
            '{{%investor_employee}}',
            'investor_id',
            '{{%investors}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-investor_employee-employee_id',
            '{{%investor_employee}}',
            'employee_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-investor_employee-assigned_by',
            '{{%investor_employee}}',
            'assigned_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex(
            'idx-investor_employee-unique',
            '{{%investor_employee}}',
            ['investor_id', 'employee_id'],
            true
        );

        $this->createIndex('idx-investor_employee-investor_id', '{{%investor_employee}}', 'investor_id');
        $this->createIndex('idx-investor_employee-employee_id', '{{%investor_employee}}', 'employee_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-investor_employee-assigned_by', '{{%investor_employee}}');
        $this->dropForeignKey('fk-investor_employee-employee_id', '{{%investor_employee}}');
        $this->dropForeignKey('fk-investor_employee-investor_id', '{{%investor_employee}}');
        $this->dropIndex('idx-investor_employee-unique', '{{%investor_employee}}');
        $this->dropIndex('idx-investor_employee-investor_id', '{{%investor_employee}}');
        $this->dropIndex('idx-investor_employee-employee_id', '{{%investor_employee}}');
        $this->dropTable('{{%investor_employee}}');
    }
}
