<?php

use yii\db\Migration;

class m260609_121806_create_scheduled_calls extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%scheduled_calls}}', [
            'id'          => $this->primaryKey(),
            'company_id'  => $this->integer()->notNull(),
            'operator_id' => $this->integer()->notNull(),
            'candidate_id'=> $this->integer()->notNull(),
            'scheduled_at'=> $this->integer()->notNull(),
            'comment'     => $this->string(500)->null()->defaultValue(null),
            'is_done'     => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'created_at'  => $this->integer()->notNull(),
            'updated_at'  => $this->integer()->notNull(),
            'notified_at' => $this->integer()->null()->defaultValue(null),
        ]);

        $this->createIndex('idx-sc-operator', '{{%scheduled_calls}}', 'operator_id');
        $this->createIndex('idx-sc-candidate', '{{%scheduled_calls}}', 'candidate_id');
        $this->createIndex('idx-sc-scheduled', '{{%scheduled_calls}}', 'scheduled_at');
        $this->createIndex('idx-sc-notified', '{{%scheduled_calls}}', 'notified_at');
        $this->createIndex('idx-sc-company-done', '{{%scheduled_calls}}', ['company_id', 'is_done']);

        $this->addForeignKey(
            'fk-sc-operator',
            '{{%scheduled_calls}}',
            'operator_id',
            '{{%user}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-sc-candidate',
            '{{%scheduled_calls}}',
            'candidate_id',
            '{{%user}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-sc-operator', '{{%scheduled_calls}}');
        $this->dropForeignKey('fk-sc-candidate', '{{%scheduled_calls}}');
        $this->dropTable('{{%scheduled_calls}}');
    }
}
