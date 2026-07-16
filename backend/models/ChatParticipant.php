<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "chat_participants".
 *
 * @property int $id
 * @property string $chat_id
 * @property int $user_id
 * @property int $joined_at
 * @property int|null $left_at
 *
 * Relations:
 * @property Chat $chat
 * @property User $user
 */
class ChatParticipant extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chat_participants';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'joined_at',
                'updatedAtAttribute' => false,
                'value' => function () { return time(); },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['chat_id', 'user_id'], 'required'],
            [['user_id', 'joined_at', 'left_at'], 'integer'],
            [['chat_id'], 'string', 'max' => 36],
            [['left_at'], 'default', 'value' => null],
            [['chat_id', 'user_id'], 'unique', 'targetAttribute' => ['chat_id', 'user_id']],
            [['chat_id'], 'exist', 'skipOnError' => true, 'targetClass' => Chat::class, 'targetAttribute' => ['chat_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['left_at'], 'compare', 'compareAttribute' => 'joined_at', 'operator' => '>=', 'skipOnEmpty' => true],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'chat_id' => 'Chat ID',
            'user_id' => 'User ID',
            'joined_at' => 'Joined At',
            'left_at' => 'Left At',
        ];
    }

    /**
     * Gets query for [[Chat]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChat()
    {
        return $this->hasOne(Chat::class, ['id' => 'chat_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Check if participant is active (hasn't left the chat)
     * @return bool
     */
    public function isActive()
    {
        return $this->left_at === null;
    }

    /**
     * Check if participant has left the chat
     * @return bool
     */
    public function hasLeft()
    {
        return $this->left_at !== null;
    }

    /**
     * Leave the chat
     * @return bool
     */
    public function leaveChat()
    {
        if ($this->left_at === null) {
            $this->left_at = time();
            return $this->save(false);
        }
        return true;
    }

    /**
     * Rejoin the chat (set left_at to null)
     * @return bool
     */
    public function rejoinChat()
    {
        if ($this->left_at !== null) {
            $this->left_at = null;
            return $this->save(false);
        }
        return true;
    }

    /**
     * Get participation duration in seconds
     * @return int|null
     */
    public function getParticipationDuration()
    {
        $endTime = $this->left_at ?? time();
        return $endTime - $this->joined_at;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && empty($this->joined_at)) {
                $this->joined_at = time();
            }
            return true;
        }
        return false;
    }

    /**
     * Find active participant by chat and user
     * @param string $chatId
     * @param int $userId
     * @return static|null
     */
    public static function findActiveParticipant($chatId, $userId)
    {
        return static::findOne([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'left_at' => null,
        ]);
    }

    /**
     * Get all active participants for a chat
     * @param string $chatId
     * @return static[]
     */
    public static function findActiveParticipantsByChat($chatId)
    {
        return static::find()
            ->where(['chat_id' => $chatId, 'left_at' => null])
            ->all();
    }
}