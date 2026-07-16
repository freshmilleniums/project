<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%reminders}}`.
 */
class m250716_001841_create_reminders_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%reminders}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(255)->notNull()->unique(),
            'text' => $this->text(),
        ]);

        $this->createIndex(
            'idx-reminders-code',
            '{{%reminders}}',
            'code'
        );

        $this->batchInsert('{{%reminders}}', ['code', 'text'], [
            ['REM1',  "We noticed you haven't logged into your account yet. Please log in to get started."],
            ['REM2',  "This is your final reminder to log into your personal account and begin the process."],
            ['REM3',  "Your contract is ready for review. Please log in and sign it to proceed."],
            ['REM4',  "Your contract is still awaiting your signature. Please sign it to continue."],
            ['REM5',  "This is your final reminder to sign the contract. Please take action immediately."],
            ['REM6',  "You have a training module waiting. Please log in and continue your training."],
            ['REM7',  "Your training module is still incomplete. Please log in and finish it as soon as possible."],
            ['REM8',  "Your final assignment is waiting. Please log in and complete it."],
            ['REM9',  "Your final assignment is still incomplete. Please log in and finish it."],
            ['REM10', "This is your final reminder to complete the assignment. Please take action immediately."],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%reminders}}');
    }
}
