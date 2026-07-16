<?php

use yii\db\Migration;

class m251220_183457_add_config_update_fields_to_companies extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'need_config_update', $this->tinyInteger(1)->notNull()->defaultValue(0));
        $this->addColumn('{{%companies}}', 'previous_url', $this->string(255)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'previous_url');
        $this->dropColumn('{{%companies}}', 'need_config_update');
    }
}
