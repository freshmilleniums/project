<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%investors}}`.
 */
class m260317_235921_create_investors_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%investors}}', [
            'id' => $this->primaryKey(),
            'first_name' => $this->string(255)->notNull(),
            'last_name' => $this->string(255)->notNull(),
            'email' => $this->string(255)->notNull(),
            'address' => $this->string(500)->null(),
            'net_value' => $this->decimal(15, 2)->null(),
            'investor_type' => $this->tinyInteger()->null(),
            'comment' => $this->string(2000)->null(),
            'created_by' => $this->integer()->null(),
            'company_id' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-investors-created_by',
            '{{%investors}}',
            'created_by',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx-investors-investor_type', '{{%investors}}', 'investor_type');
        $this->createIndex('idx-investors-created_by', '{{%investors}}', 'created_by');
        $this->createIndex('idx-investors-email', '{{%investors}}', 'email');
        $this->createIndex('idx-investors-company_id', '{{%investors}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-investors-created_by', '{{%investors}}');
        $this->dropIndex('idx-investors-investor_type', '{{%investors}}');
        $this->dropIndex('idx-investors-created_by', '{{%investors}}');
        $this->dropIndex('idx-investors-email', '{{%investors}}');
        $this->dropIndex('idx-investors-company_id', '{{%investors}}');
        $this->dropTable('{{%investors}}');
    }
}
