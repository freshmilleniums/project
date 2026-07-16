<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%settings}}`.
 */
class m250719_001129_create_settings_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable('settings', [
            'id' => $this->primaryKey(),
            'contract_text' => $this->text()->null(),
        ]);

        $this->insert('settings', [
            'id' => 1,
            'contract_text' => null,
        ]);
    }

    public function down()
    {
        $this->dropTable('settings');
    }
}
