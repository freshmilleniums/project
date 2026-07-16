<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use common\models\User;

/**
 * This is the model class for table "notification".
 *
 * @property int $id
 * @property int $user_id
 * @property string $text
 * @property int $read
 * @property int $created_at
 * @property int|null $resent_at
 * @property int|null $resent_by
 *
 * @property User $user
 * @property User $resentBy
 */
class NotificationModel extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%notification}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'text', 'created_at'], 'required'],
            [['user_id', 'read', 'created_at', 'resent_at', 'resent_by'], 'integer'],
            [['text'], 'string', 'max' => 500],
            [['read'], 'default', 'value' => 0],
            [['read'], 'in', 'range' => [0, 1]],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            [['resent_by'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'text' => 'Text',
            'read' => 'Read',
            'created_at' => 'Created At',
            'resent_at' => 'Resent At',
            'resent_by' => 'Resent By',
        ];
    }

    /**
     * Get user relation
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Get resent by user relation
     * @return \yii\db\ActiveQuery
     */
    public function getResentBy()
    {
        return $this->hasOne(User::class, ['id' => 'resent_by']);
    }

    /**
     * Check if notification is unread
     * @return bool
     */
    public function isUnread()
    {
        return $this->read == 0;
    }

}