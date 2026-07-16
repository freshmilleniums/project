<?php

use yii\db\Migration;

class m250811_182558_add_substatus_changed_at_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'substatus_changed_at', $this->integer(11)->null());

        $this->createIndex(
            'idx-user-substatus_changed_at',
            '{{%user}}',
            'substatus_changed_at'
        );
    }

    public function safeDown()
    {
        $this->dropIndex(
            'idx-user-substatus_changed_at',
            '{{%user}}'
        );

        $this->dropColumn('{{%user}}', 'substatus_changed_at');
    }
}
