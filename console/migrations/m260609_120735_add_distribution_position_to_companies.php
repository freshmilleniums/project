<?php

use yii\db\Migration;

class m260609_120735_add_distribution_position_to_companies extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%companies}}',
            'distribution_position',
            $this->integer()->unsigned()->notNull()->defaultValue(0)
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'distribution_position');
    }
}
