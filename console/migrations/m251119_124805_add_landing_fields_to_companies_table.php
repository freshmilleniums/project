<?php

use yii\db\Migration;

class m251119_124805_add_landing_fields_to_companies_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->addColumn('{{%companies}}', 'landing_url', $this->string(255)->notNull());
        $this->addColumn('{{%companies}}', 'landing_api_key', $this->string(64)->notNull()->unique());

        $this->createIndex(
            'idx-companies-landing_api_key',
            '{{%companies}}',
            'landing_api_key'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-companies-landing_api_key', '{{%companies}}');
        $this->dropColumn('{{%companies}}', 'landing_api_key');
        $this->dropColumn('{{%companies}}', 'landing_url');
    }
}
