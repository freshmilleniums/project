<?php

use yii\db\Migration;

class m260609_120721_create_call_center_distribution extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%call_center_distribution}}', [
            'id'          => $this->primaryKey(),
            'company_id'  => $this->integer()->notNull(),
            'operator_id' => $this->integer()->notNull(),
            'percentage'  => $this->tinyInteger()->unsigned()->null()->defaultValue(null),
            'is_custom'   => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'created_at'  => $this->integer()->notNull(),
            'updated_at'  => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-ccd-company', '{{%call_center_distribution}}', 'company_id');
        $this->createIndex('idx-ccd-operator', '{{%call_center_distribution}}', 'operator_id');
        $this->createIndex(
            'uq-ccd-company-operator',
            '{{%call_center_distribution}}',
            ['company_id', 'operator_id'],
            true
        );

        $this->addForeignKey(
            'fk-ccd-operator',
            '{{%call_center_distribution}}',
            'operator_id',
            '{{%user}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-ccd-operator', '{{%call_center_distribution}}');
        $this->dropTable('{{%call_center_distribution}}');
    }
}
