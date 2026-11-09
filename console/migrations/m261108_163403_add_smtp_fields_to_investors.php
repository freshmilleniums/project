<?php

use yii\db\Migration;

class m261108_163403_add_smtp_fields_to_investors extends Migration
{
    public function safeUp()
    {
        $this->addColumn('investors', 'smtp_host', $this->string(255)->null());
        $this->addColumn('investors', 'smtp_port', $this->smallInteger()->unsigned()->null()->defaultValue(587));
        $this->addColumn('investors', 'smtp_encryption', $this->tinyInteger()->unsigned()->null()->defaultValue(2));
        $this->addColumn('investors', 'smtp_username', $this->string(255)->null());
        $this->addColumn('investors', 'smtp_password', $this->text()->null());
        $this->addColumn('investors', 'smtp_from_email', $this->string(255)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('investors', 'smtp_from_email');
        $this->dropColumn('investors', 'smtp_password');
        $this->dropColumn('investors', 'smtp_username');
        $this->dropColumn('investors', 'smtp_encryption');
        $this->dropColumn('investors', 'smtp_port');
        $this->dropColumn('investors', 'smtp_host');
    }
}
