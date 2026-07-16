<?php

use yii\db\Migration;

class m250512_122453_add_user_fields extends Migration
{
    public function safeUp()
    {
        $this->addColumn('user', 'first_name', $this->string()->notNull());
        $this->addColumn('user', 'last_name', $this->string()->notNull());
        $this->addColumn('user', 'address', $this->string());
        $this->addColumn('user', 'phone_number', $this->string());
        $this->addColumn('user', 'city', $this->string());
        $this->addColumn('user', 'state', $this->string(2));
        $this->addColumn('user', 'zip_code', $this->string());
    }

    public function safeDown()
    {
        $this->dropColumn('user', 'first_name');
        $this->dropColumn('user', 'last_name');
        $this->dropColumn('user', 'address');
        $this->dropColumn('user', 'phone_number');
        $this->dropColumn('user', 'city');
        $this->dropColumn('user', 'state');
        $this->dropColumn('user', 'zip_code');
    }
}
