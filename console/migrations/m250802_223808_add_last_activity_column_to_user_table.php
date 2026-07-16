<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%user}}`.
 */
class m250802_223808_add_last_activity_column_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'last_activity', $this->integer(11)->notNull()->defaultValue(0));
        $this->createIndex('idx-user-last_activity', '{{%user}}', 'last_activity');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-user-last_activity', '{{%user}}');
        $this->dropColumn('{{%user}}', 'last_activity');
    }
}
