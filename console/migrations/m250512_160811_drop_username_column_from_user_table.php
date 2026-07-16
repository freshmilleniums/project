<?php

use yii\db\Migration;

/**
 * Handles dropping columns from table `{{%user}}`.
 */
class m250512_160811_drop_username_column_from_user_table extends Migration
{
    public function safeUp()
    {
        $this->dropColumn('{{%user}}', 'username');
    }

    public function safeDown()
    {
        $this->addColumn('{{%user}}', 'username', $this->string()->unique());
    }
}
