<?php

use yii\db\Migration;

class m251005_000337_add_company_id_to_main_tables extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'company_id', $this->integer()->notNull()->defaultValue(0));
        $this->createIndex('idx-user-company_id', '{{%user}}', 'company_id');

        $this->addColumn('{{%chats}}', 'company_id', $this->integer()->notNull()->defaultValue(0));
        $this->createIndex('idx-chats-company_id', '{{%chats}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-company_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'company_id');

        $this->dropIndex('idx-chats-company_id', '{{%chats}}');
        $this->dropColumn('{{%chats}}', 'company_id');
    }
}
