<?php

use yii\db\Migration;

class m260609_121708_create_call_center_scripts extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%call_center_scripts}}', [
            'id'         => $this->primaryKey(),
            'company_id' => $this->integer()->notNull(),
            'title'      => $this->string(255)->notNull(),
            'content'    => $this->text()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active'  => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'created_by' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-ccs-company', '{{%call_center_scripts}}', 'company_id');
        $this->createIndex('idx-ccs-sort', '{{%call_center_scripts}}', ['company_id', 'sort_order']);

        $this->addForeignKey(
            'fk-ccs-created-by',
            '{{%call_center_scripts}}',
            'created_by',
            '{{%user}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-ccs-created-by', '{{%call_center_scripts}}');
        $this->dropTable('{{%call_center_scripts}}');
    }
}
