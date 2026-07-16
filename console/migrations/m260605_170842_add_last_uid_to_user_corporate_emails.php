<?php

use yii\db\Migration;

class m260605_170842_add_last_uid_to_user_corporate_emails extends Migration
{
    public function up()
    {
        $this->addColumn('{{%user_corporate_emails}}', 'last_uid',
            $this->integer()->unsigned()->notNull()->defaultValue(0)->after('is_active'));

        $this->addColumn('{{%user_corporate_emails}}', 'last_sync_at',
            $this->integer()->unsigned()->null()->after('last_uid'));
    }

    public function down()
    {
        $this->dropColumn('{{%user_corporate_emails}}', 'last_uid');
        $this->dropColumn('{{%user_corporate_emails}}', 'last_sync_at');
    }
}
