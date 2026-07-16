<?php

use yii\db\Migration;

class m250601_162027_add_substatus_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'substatus', $this->integer()->notNull()->defaultValue(1));
        $this->createIndex('idx-user-substatus', '{{%user}}', 'substatus');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-substatus', '{{%user}}');
        $this->dropColumn('{{%user}}', 'substatus');
    }
}
