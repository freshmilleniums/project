<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_comments}}`.
 */
class m250615_225057_create_user_comments_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%user_comments}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'commented_by' => $this->integer()->notNull(),
            'comment' => $this->string(1000)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // Add foreign keys
        $this->addForeignKey(
            'fk-user_comments-user_id',
            '{{%user_comments}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-user_comments-commented_by',
            '{{%user_comments}}',
            'commented_by',
            '{{%user}}',
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Drop foreign keys
        $this->dropForeignKey('fk-user_comments-user_id', '{{%user_comments}}');
        $this->dropForeignKey('fk-user_comments-commented_by', '{{%user_comments}}');

        // Drop table
        $this->dropTable('{{%user_comments}}');
    }
}
