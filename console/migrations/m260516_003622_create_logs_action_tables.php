<?php

use yii\db\Migration;

class m260516_003622_create_logs_action_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%logs_action}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'entity_type' => $this->string(50)->notNull(),
            'entity_id' => $this->integer()->notNull(),
            'action' => $this->string(100)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-logs_action-entity', '{{%logs_action}}', ['entity_type', 'entity_id']);
        $this->createIndex('idx-logs_action-user_id', '{{%logs_action}}', 'user_id');
        $this->createIndex('idx-logs_action-action', '{{%logs_action}}', 'action');
        $this->createIndex('idx-logs_action-created_at', '{{%logs_action}}', 'created_at');
        $this->createIndex('idx-logs_action-entity_type', '{{%logs_action}}', 'entity_type');

        $this->addForeignKey(
            'fk-logs_action-user_id',
            '{{%logs_action}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%logs_action_details}}', [
            'id' => $this->primaryKey(),
            'log_id' => $this->integer()->notNull(),
            'old_values' => $this->text()->null(),
            'new_values' => $this->text()->null(),
        ]);

        $this->createIndex('idx-logs_action_details-log_id', '{{%logs_action_details}}', 'log_id');

        $this->addForeignKey(
            'fk-logs_action_details-log_id',
            '{{%logs_action_details}}',
            'log_id',
            '{{%logs_action}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-logs_action_details-log_id', '{{%logs_action_details}}');
        $this->dropTable('{{%logs_action_details}}');

        $this->dropForeignKey('fk-logs_action-user_id', '{{%logs_action}}');
        $this->dropTable('{{%logs_action}}');
    }
}
