<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%email_accounts}}`.
 */
class m260401_105234_create_email_accounts_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%email_accounts}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->notNull(),
            'label' => $this->string(255)->null(),

            'username' => $this->string(255)->notNull(),
            'password' => $this->text()->notNull(),

            'imap_host' => $this->string(255)->notNull(),
            'imap_port' => $this->smallInteger()->unsigned()->notNull()->defaultValue(993),
            'imap_encryption' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(1),

            'smtp_host' => $this->string(255)->notNull(),
            'smtp_port' => $this->smallInteger()->unsigned()->notNull()->defaultValue(587),
            'smtp_encryption' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(2),

            'is_corporate' => $this->boolean()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'company_id' => $this->integer()->notNull()->defaultValue(0),

            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex('unique_email', '{{%email_accounts}}', 'email', true);
        $this->createIndex('idx_is_corporate', '{{%email_accounts}}', 'is_corporate');
        $this->createIndex('idx_is_active', '{{%email_accounts}}', 'is_active');
        $this->createIndex('idx-email_accounts-company_id', '{{%email_accounts}}', 'company_id');
    }

    public function safeDown()
    {
        $this->dropIndex('unique_email', '{{%email_accounts}}');
        $this->dropIndex('idx_is_corporate', '{{%email_accounts}}');
        $this->dropIndex('idx_is_active', '{{%email_accounts}}');
        $this->dropIndex('idx-email_accounts-company_id', '{{%email_accounts}}');

        $this->dropTable('{{%email_accounts}}');
    }
}
