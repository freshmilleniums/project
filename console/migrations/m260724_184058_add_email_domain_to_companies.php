<?php

use yii\db\Migration;

class m260724_184058_add_email_domain_to_companies extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%companies}}',
            'email_domain',
            $this->string(255)->null()
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'email_domain');
    }
}
