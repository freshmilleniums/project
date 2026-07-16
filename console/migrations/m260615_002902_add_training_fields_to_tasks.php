<?php

use yii\db\Migration;

class m260615_002902_add_training_fields_to_tasks extends Migration
{
    public function up()
    {
        $this->addColumn('{{%tasks}}', 'is_training', $this->tinyInteger(1)->notNull()->defaultValue(0));
        $this->addColumn('{{%tasks}}', 'training_module_id', $this->integer()->null());
        $this->addColumn('{{%tasks}}', 'training_employee_id', $this->integer()->null());
    }

    public function down()
    {
        $this->dropColumn('{{%tasks}}', 'is_training');
        $this->dropColumn('{{%tasks}}', 'training_module_id');
        $this->dropColumn('{{%tasks}}', 'training_employee_id');
    }
}
