<?php

use yii\db\Migration;

class m260826_182819_add_smtp_from_email_to_companies extends Migration
{
    public function up()
    {
        $this->addColumn('companies', 'smtp_from_email', $this->string(255)->null()->after('smtp_password'));
    }

    public function down()
    {
        $this->dropColumn('companies', 'smtp_from_email');
    }
}
