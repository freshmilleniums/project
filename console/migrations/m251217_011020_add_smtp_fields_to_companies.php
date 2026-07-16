<?php

use yii\db\Migration;

class m251217_011020_add_smtp_fields_to_companies extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'smtp_server', $this->string(255)->null());
        $this->addColumn('{{%companies}}', 'smtp_port', $this->integer()->null());
        $this->addColumn('{{%companies}}', 'smtp_login', $this->string(255)->null());
        $this->addColumn('{{%companies}}', 'smtp_password', $this->string(255)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'smtp_password');
        $this->dropColumn('{{%companies}}', 'smtp_login');
        $this->dropColumn('{{%companies}}', 'smtp_port');
        $this->dropColumn('{{%companies}}', 'smtp_server');
    }
}
