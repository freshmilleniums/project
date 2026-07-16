<?php

use yii\db\Migration;

class m251006_155719_create_logs_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%logs_company}}', [
            'id' => $this->primaryKey(),
            'company_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'action_type' => $this->string(50)->notNull(),
            'date' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-logs_company-company_id', '{{%logs_company}}', 'company_id');
        $this->createIndex('idx-logs_company-user_id', '{{%logs_company}}', 'user_id');
        $this->createIndex('idx-logs_company-action_type', '{{%logs_company}}', 'action_type');

        $this->createTable('{{%logs_company_details}}', [
            'id' => $this->primaryKey(),
            'logs_company_id' => $this->integer()->notNull(),
            'data' => $this->text(),
            'data_type' => $this->string(50)->notNull(),
        ]);

        $this->createIndex('idx-logs_company_details-logs_company_id', '{{%logs_company_details}}', 'logs_company_id');
        $this->addForeignKey(
            'fk-logs_company_details-logs_company_id',
            '{{%logs_company_details}}',
            'logs_company_id',
            '{{%logs_company}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%logs_admin}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'action_type' => $this->string(50)->notNull(),
            'section' => $this->string(50)->notNull(),
            'date' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-logs_admin-user_id', '{{%logs_admin}}', 'user_id');
        $this->createIndex('idx-logs_admin-action_type', '{{%logs_admin}}', 'action_type');
        $this->createIndex('idx-logs_admin-section', '{{%logs_admin}}', 'section');

        $this->createTable('{{%logs_admin_details}}', [
            'id' => $this->primaryKey(),
            'logs_admin_id' => $this->integer()->notNull(),
            'data' => $this->text(),
            'data_type' => $this->string(50)->notNull(),
        ]);

        $this->createIndex('idx-logs_admin_details-logs_admin_id', '{{%logs_admin_details}}', 'logs_admin_id');
        $this->addForeignKey(
            'fk-logs_admin_details-logs_admin_id',
            '{{%logs_admin_details}}',
            'logs_admin_id',
            '{{%logs_admin}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-logs_admin_details-logs_admin_id', '{{%logs_admin_details}}');
        $this->dropTable('{{%logs_admin_details}}');
        $this->dropTable('{{%logs_admin}}');

        $this->dropForeignKey('fk-logs_company_details-logs_company_id', '{{%logs_company_details}}');
        $this->dropTable('{{%logs_company_details}}');
        $this->dropTable('{{%logs_company}}');
    }
}
