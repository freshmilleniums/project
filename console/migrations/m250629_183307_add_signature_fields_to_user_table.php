<?php

use yii\db\Migration;

class m250629_183307_add_signature_fields_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'sign_signature_style', $this->string(50));
        $this->addColumn('{{%user}}', 'sign_signature_date', $this->date()->null());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'sign_signature_style');
        $this->dropColumn('{{%user}}', 'sign_signature_date');
    }
}
