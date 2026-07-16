<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%companies}}`.
 */
class m251005_000157_create_companies_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%companies}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'url' => $this->string(255),
            'status' => $this->integer()->notNull()->defaultValue(0),
            'administrator_id' => $this->integer(),
        ]);

        $this->createIndex(
            'idx-companies-administrator_id',
            '{{%companies}}',
            'administrator_id'
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-companies-administrator_id', '{{%companies}}');
        $this->dropTable('{{%companies}}');
    }
}
