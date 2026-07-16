<?php

use yii\db\Migration;

class m260228_132941_add_employee_fields_to_user extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'sequential_number', $this->integer()->unique());
        $this->addColumn('{{%user}}', 'position_title', $this->string(255));
        $this->addColumn('{{%user}}', 'country', $this->string(100));
        $this->addColumn('{{%user}}', 'home_phone', $this->string(50));
        $this->addColumn('{{%user}}', 'current_project_id', $this->integer());
        $this->addColumn('{{%user}}', 'hr_source', $this->string(255));
        $this->addColumn('{{%user}}', 'administrator_id', $this->integer());
        $this->addColumn('{{%user}}', 'total_time_today', $this->integer()->defaultValue(0));
        $this->addColumn('{{%user}}', 'is_online', $this->boolean()->defaultValue(0));

        $this->alterColumn('{{%user}}', 'state', $this->string(100));

        $this->createIndex('idx-user-sequential_number', '{{%user}}', 'sequential_number');
        $this->createIndex('idx-user-administrator_id', '{{%user}}', 'administrator_id');
        $this->createIndex('idx-user-current_project_id', '{{%user}}', 'current_project_id');
        $this->createIndex('idx-user-is_online', '{{%user}}', 'is_online');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-sequential_number', '{{%user}}');
        $this->dropIndex('idx-user-administrator_id', '{{%user}}');
        $this->dropIndex('idx-user-current_project_id', '{{%user}}');
        $this->dropIndex('idx-user-is_online', '{{%user}}');

        $this->dropColumn('{{%user}}', 'sequential_number');
        $this->dropColumn('{{%user}}', 'position_title');
        $this->dropColumn('{{%user}}', 'country');
        $this->dropColumn('{{%user}}', 'home_phone');
        $this->dropColumn('{{%user}}', 'current_project_id');
        $this->dropColumn('{{%user}}', 'hr_source');
        $this->dropColumn('{{%user}}', 'administrator_id');
        $this->dropColumn('{{%user}}', 'total_time_today');
        $this->dropColumn('{{%user}}', 'is_online');

        $this->alterColumn('{{%user}}', 'state', $this->string(2));
    }
}
