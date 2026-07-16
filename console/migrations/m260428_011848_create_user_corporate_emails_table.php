<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_corporate_emails}}`.
 */
class m260428_011848_create_user_corporate_emails_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%user_corporate_emails}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->unique(),
            'email' => $this->string(255)->notNull()->unique(),
            'password' => $this->text()->notNull(),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_user_corporate_email_user',
            '{{%user_corporate_emails}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('unique_user_id', '{{%user_corporate_emails}}', 'user_id', true);
        $this->createIndex('unique_email', '{{%user_corporate_emails}}', 'email', true);
        $this->createIndex('idx_is_active', '{{%user_corporate_emails}}', 'is_active');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_user_corporate_email_user', '{{%user_corporate_emails}}');

        $this->dropIndex('unique_user_id', '{{%user_corporate_emails}}');
        $this->dropIndex('unique_email', '{{%user_corporate_emails}}');
        $this->dropIndex('idx_is_active', '{{%user_corporate_emails}}');

        $this->dropTable('{{%user_corporate_emails}}');
    }
}
