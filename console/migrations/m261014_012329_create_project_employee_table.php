<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%project_employee}}`.
 */
class m261014_012329_create_project_employee_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('project_employee', [
            'id'          => $this->primaryKey(),
            'project_id'  => $this->integer()->notNull(),
            'employee_id' => $this->integer()->notNull(),
            'assigned_by' => $this->integer()->null(),
            'assigned_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_project_employee_unique', 'project_employee', ['project_id', 'employee_id'], true);
        $this->createIndex('idx_project_employee_project', 'project_employee', 'project_id');
        $this->createIndex('idx_project_employee_employee', 'project_employee', 'employee_id');

        // Migrate existing data
        $this->execute("
            INSERT INTO project_employee (project_id, employee_id, assigned_by, assigned_at)
            SELECT id, employee_id, created_by, created_at
            FROM projects
            WHERE employee_id IS NOT NULL
        ");

        $this->dropForeignKey('fk-projects-employee_id', 'projects');
        $this->dropColumn('projects', 'employee_id');
    }

    public function safeDown()
    {
        $this->dropTable('project_employee');
        $this->addColumn('projects', 'employee_id', $this->integer()->null());
        $this->addForeignKey('fk-projects-employee_id', 'projects', 'employee_id', 'user', 'id', 'SET NULL');
    }
}
