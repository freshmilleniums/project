<?php

use yii\db\Migration;

class m250629_205307_add_contract_pdf_path_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'contract_pdf_path', $this->string(500)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'contract_pdf_path');
    }
}
