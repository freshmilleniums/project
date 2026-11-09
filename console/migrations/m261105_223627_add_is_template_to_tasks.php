<?php

use yii\db\Migration;

class m261105_223627_add_is_template_to_tasks extends Migration
{
    public function safeUp()
    {
        $this->addColumn('tasks', 'is_template', $this->tinyInteger(1)->notNull()->defaultValue(0));
        $this->createIndex('idx_tasks_assigned_to', 'tasks', 'assigned_to');
        $this->createIndex('idx_tasks_is_template', 'tasks', 'is_template');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_tasks_is_template', 'tasks');
        $this->dropIndex('idx_tasks_assigned_to', 'tasks');
        $this->dropColumn('tasks', 'is_template');
    }
}
