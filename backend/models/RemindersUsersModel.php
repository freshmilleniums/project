<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "reminders_users".
 *
 * @property int $id
 * @property int $reminder_id
 * @property string $reminder_code
 * @property int $user_id
 * @property string|null $text
 * @property int $read
 * @property int $created_at
 */
class RemindersUsersModel extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%reminders_users}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['reminder_id', 'reminder_code', 'user_id', 'created_at'], 'required'],
            [['reminder_id', 'user_id', 'read', 'created_at'], 'integer'],
            [['text'], 'string'],
            [['reminder_code'], 'string', 'max' => 255],
            [['reminder_code'], 'unique'],
            [['read'], 'default', 'value' => 0],
            [['read'], 'in', 'range' => [0, 1]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'reminder_id' => 'Reminder ID',
            'reminder_code' => 'Reminder Code',
            'user_id' => 'User ID',
            'text' => 'Text',
            'read' => 'Read',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Check if reminder is unread
     * @return bool
     */
    public function isUnread()
    {
        return $this->read == 0;
    }
}