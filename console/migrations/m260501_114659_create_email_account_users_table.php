<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%email_account_users}}`.
 */
class m260501_114659_create_email_account_users_table extends Migration
{

    public function safeUp()
    {
        $this->createTable('{{%email_account_users}}', [
            'id' => $this->primaryKey(),
            'email_account_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_email_account_users_account',
            '{{%email_account_users}}',
            'email_account_id',
            '{{%email_accounts}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_email_account_users_user',
            '{{%email_account_users}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'unique_account_user',
            '{{%email_account_users}}',
            ['email_account_id', 'user_id'],
            true
        );

        $this->createIndex('idx_user_id', '{{%email_account_users}}', 'user_id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_email_account_users_account', '{{%email_account_users}}');
        $this->dropForeignKey('fk_email_account_users_user', '{{%email_account_users}}');
        $this->dropIndex('unique_account_user', '{{%email_account_users}}');
        $this->dropIndex('idx_user_id', '{{%email_account_users}}');
        $this->dropTable('{{%email_account_users}}');
    }
}
