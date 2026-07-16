<?php

use yii\db\Migration;

class m260609_083153_add_total_time_all_to_user extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'total_time_all', $this->integer()->notNull()->defaultValue(0));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'total_time_all');
    }
}
