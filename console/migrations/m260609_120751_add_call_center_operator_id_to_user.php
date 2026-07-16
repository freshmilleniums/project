<?php

use yii\db\Migration;

class m260609_120751_add_call_center_operator_id_to_user extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%user}}',
            'call_center_operator_id',
            $this->integer()->null()->defaultValue(null)->after('administrator_id')
        );

        $this->createIndex('idx-user-cc-operator', '{{%user}}', 'call_center_operator_id');

        $this->addForeignKey(
            'fk-user-cc-operator',
            '{{%user}}',
            'call_center_operator_id',
            '{{%user}}',
            'id',
            'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-user-cc-operator', '{{%user}}');
        $this->dropIndex('idx-user-cc-operator', '{{%user}}');
        $this->dropColumn('{{%user}}', 'call_center_operator_id');
    }
}
