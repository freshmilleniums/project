<?php

use yii\db\Migration;

class m260425_174627_create_training_system_tables extends Migration
{
    public function safeUp()
    {
        // Training modules table
        $this->createTable('{{%training_modules}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'content' => $this->text(),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'passing_score' => $this->integer()->notNull()->defaultValue(80),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_tm_sort', '{{%training_modules}}', 'sort');
        $this->createIndex('idx_tm_active', '{{%training_modules}}', 'is_active');

        // Training module questions table
        $this->createTable('{{%training_module_questions}}', [
            'id' => $this->primaryKey(),
            'module_id' => $this->integer()->notNull(),
            'question_text' => $this->string(500)->notNull(),
            'type' => $this->tinyInteger()->notNull(),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'has_correct_answer' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_tmq_module', '{{%training_module_questions}}', 'module_id');
        $this->createIndex('idx_tmq_sort', '{{%training_module_questions}}', 'sort');

        $this->addForeignKey(
            'fk_tmq_module_id',
            '{{%training_module_questions}}', 'module_id',
            '{{%training_modules}}', 'id',
            'CASCADE', 'CASCADE'
        );

        // Training question options table
        $this->createTable('{{%training_question_options}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'option_text' => $this->string(255)->notNull(),
            'is_correct' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'sort' => $this->integer()->notNull()->defaultValue(0),
        ]);

        $this->createIndex('idx_tqo_question', '{{%training_question_options}}', 'question_id');
        $this->createIndex('idx_tqo_is_correct', '{{%training_question_options}}', 'is_correct');

        $this->addForeignKey(
            'fk_tqo_question_id',
            '{{%training_question_options}}', 'question_id',
            '{{%training_module_questions}}', 'id',
            'CASCADE', 'CASCADE'
        );

        // User training progress table (1 record per user)
        $this->createTable('{{%user_training_progress}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'current_module_id' => $this->integer()->notNull(),
            'current_module_attempts' => $this->integer()->notNull()->defaultValue(0),
            'last_attempt_at' => $this->integer(),
            'last_attempt_score' => $this->decimal(5, 2)->defaultValue(0),
            'completed_modules' => $this->text(),
            'started_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_utp_user', '{{%user_training_progress}}', 'user_id', true);
        $this->createIndex('idx_utp_current_module', '{{%user_training_progress}}', 'current_module_id');
        $this->createIndex('idx_utp_last_attempt', '{{%user_training_progress}}', 'last_attempt_at');

        $this->addForeignKey(
            'fk_utp_user_id',
            '{{%user_training_progress}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            'fk_utp_module_id',
            '{{%user_training_progress}}', 'current_module_id',
            '{{%training_modules}}', 'id',
            'RESTRICT', 'CASCADE'
        );

        // User module answers table
        $this->createTable('{{%user_module_answers}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'module_id' => $this->integer()->notNull(),
            'question_id' => $this->integer()->notNull(),
            'question_text' => $this->string(500)->notNull(),
            'answer_data' => $this->text(),
            'is_correct' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_uma_user', '{{%user_module_answers}}', 'user_id');
        $this->createIndex('idx_uma_module', '{{%user_module_answers}}', 'module_id');
        $this->createIndex('idx_uma_question', '{{%user_module_answers}}', 'question_id');

        $this->addForeignKey(
            'fk_uma_user_id',
            '{{%user_module_answers}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_uma_user_id', '{{%user_module_answers}}');
        $this->dropTable('{{%user_module_answers}}');

        $this->dropForeignKey('fk_utp_module_id', '{{%user_training_progress}}');
        $this->dropForeignKey('fk_utp_user_id', '{{%user_training_progress}}');
        $this->dropTable('{{%user_training_progress}}');

        $this->dropForeignKey('fk_tqo_question_id', '{{%training_question_options}}');
        $this->dropTable('{{%training_question_options}}');

        $this->dropForeignKey('fk_tmq_module_id', '{{%training_module_questions}}');
        $this->dropTable('{{%training_module_questions}}');

        $this->dropTable('{{%training_modules}}');
    }
}
