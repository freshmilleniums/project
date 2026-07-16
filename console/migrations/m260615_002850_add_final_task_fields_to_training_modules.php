<?php

use yii\db\Migration;

class m260615_002850_add_final_task_fields_to_training_modules extends Migration
{
    public function up()
    {
        $this->addColumn('{{%training_modules}}', 'is_final_task', $this->tinyInteger(1)->notNull()->defaultValue(0));
        $this->addColumn('{{%training_modules}}', 'task_title', $this->string(255)->null());
        $this->addColumn('{{%training_modules}}', 'task_subject', $this->string(255)->null());
        $this->addColumn('{{%training_modules}}', 'task_body', $this->text()->null());
        $this->addColumn('{{%training_modules}}', 'task_file', $this->string(500)->null());
        $this->addColumn('{{%training_modules}}', 'task_deadline_hours', $this->integer()->null());
    }

    public function down()
    {
        $this->dropColumn('{{%training_modules}}', 'is_final_task');
        $this->dropColumn('{{%training_modules}}', 'task_title');
        $this->dropColumn('{{%training_modules}}', 'task_subject');
        $this->dropColumn('{{%training_modules}}', 'task_body');
        $this->dropColumn('{{%training_modules}}', 'task_file');
        $this->dropColumn('{{%training_modules}}', 'task_deadline_hours');
    }
}
