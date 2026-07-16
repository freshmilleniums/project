<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user_documents}}`.
 */
class m260605_084645_create_user_documents_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user_documents}}', [
            'id'            => $this->primaryKey(),
            'user_id'       => $this->integer()->notNull(),
            'path'          => $this->string(500)->notNull(),
            'original_name' => $this->string(255)->notNull(),
            'mime_type'     => $this->string(100)->null(),
            'file_size'     => $this->integer()->null(),
            'document_type' => $this->tinyInteger()->notNull()->defaultValue(0),
            'uploaded_by'   => $this->integer()->null(),
            'created_at'    => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-user_documents-user_id',
            '{{%user_documents}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            'fk-user_documents-uploaded_by',
            '{{%user_documents}}', 'uploaded_by',
            '{{%user}}', 'id',
            'SET NULL', 'CASCADE'
        );

        $this->createIndex('idx-user_documents-user_id', '{{%user_documents}}', 'user_id');
        $this->createIndex('idx-user_documents-uploaded_by', '{{%user_documents}}', 'uploaded_by');
        $this->createIndex('idx-user_documents-document_type', '{{%user_documents}}', 'document_type');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-user_documents-uploaded_by', '{{%user_documents}}');
        $this->dropForeignKey('fk-user_documents-user_id', '{{%user_documents}}');
        $this->dropIndex('idx-user_documents-document_type', '{{%user_documents}}');
        $this->dropIndex('idx-user_documents-uploaded_by', '{{%user_documents}}');
        $this->dropIndex('idx-user_documents-user_id', '{{%user_documents}}');
        $this->dropTable('{{%user_documents}}');
    }
}
