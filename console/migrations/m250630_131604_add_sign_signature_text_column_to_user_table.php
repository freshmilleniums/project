<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%user}}`.
 */
class m250630_131604_add_sign_signature_text_column_to_user_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'sign_signature_text', $this->string(255)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'sign_signature_text');
    }
}
