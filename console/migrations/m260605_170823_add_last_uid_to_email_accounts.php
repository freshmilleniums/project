<?php

use yii\db\Migration;

class m260605_170823_add_last_uid_to_email_accounts extends Migration
{
    public function up()
    {
        $this->addColumn('{{%email_accounts}}', 'last_uid',
            $this->integer()->unsigned()->notNull()->defaultValue(0)->after('is_active'));

        $this->addColumn('{{%email_accounts}}', 'last_sync_at',
            $this->integer()->unsigned()->null()->after('last_uid'));
    }

    public function down()
    {
        $this->dropColumn('{{%email_accounts}}', 'last_uid');
        $this->dropColumn('{{%email_accounts}}', 'last_sync_at');
    }
}
