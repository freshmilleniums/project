<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "chats".
 *
 * @property string $id
 * @property string $type
 * @property string|null $title
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $last_message_at
 * @property int|null $courier_id
 *
 * Relations:
 * @property User|null $courier
 * @property ChatParticipant[] $chatParticipants
 * @property ChatMessage[] $messages
 * @property User[] $participants
 */
class Chat extends ActiveRecord
{
    const TYPE_COURIER = 'courier';
    const TYPE_EMPLOYEE_PRIVATE = 'employee_private';
    const TYPE_EMPLOYEE_GROUP = 'employee_group';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chats';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type'], 'required'],
            [['created_at', 'updated_at', 'last_message_at', 'courier_id'], 'integer'],
            [['id'], 'string', 'max' => 36],
            [['id'], 'default', 'value' => function() { return $this->generateUuidV4(); }],
            [['created_at', 'updated_at'], 'default', 'value' => time()],
            [['type'], 'in', 'range' => [self::TYPE_COURIER, self::TYPE_EMPLOYEE_PRIVATE, self::TYPE_EMPLOYEE_GROUP]],
            [['title'], 'string', 'max' => 255],
            [['title', 'last_message_at', 'courier_id'], 'default', 'value' => null],
            [['id'], 'unique'],
            [['courier_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['courier_id' => 'id']],
            [['company_id'], 'integer'],
            [['company_id'], 'default', 'value' => function() {
                return \Yii::$app->params['company_id'] ?? null;
            }],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'title' => 'Title',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'last_message_at' => 'Last Message At',
            'courier_id' => 'Courier ID',
        ];
    }

    /**
     * Get all chat type labels
     * @return array
     */
    public static function getTypeLabels()
    {
        return [
            self::TYPE_COURIER => 'Courier Chat',
            self::TYPE_EMPLOYEE_PRIVATE => 'Employee Private Chat',
            self::TYPE_EMPLOYEE_GROUP => 'Employee Group Chat',
        ];
    }

    /**
     * Get current type label
     * @return string
     */
    public function getTypeLabel()
    {
        $labels = self::getTypeLabels();
        return $labels[$this->type] ?? 'Unknown';
    }

    public function getDefaultTitle(): string
    {
        switch ($this->type) {
            case self::TYPE_COURIER:
                return $this->courier
                    ? 'Chat with ' . trim($this->courier->first_name . ' ' . $this->courier->last_name)
                    : 'Courier Chat';
            case self::TYPE_EMPLOYEE_PRIVATE:
                return 'Private Chat';
            case self::TYPE_EMPLOYEE_GROUP:
                return 'Group Chat';
            default:
                return 'Chat';
        }
    }

    /**
     * Get type options for dropdowns
     * @return array
     */
    public static function getTypeOptions()
    {
        return self::getTypeLabels();
    }

    /**
     * Gets query for [[Courier]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCourier()
    {
        return $this->hasOne(User::class, ['id' => 'courier_id']);
    }

    /**
     * Gets query for [[ChatParticipants]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChatParticipants()
    {
        return $this->hasMany(ChatParticipant::class, ['chat_id' => 'id']);
    }

    /**
     * Gets query for [[Messages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMessages()
    {
        return $this->hasMany(ChatMessage::class, ['chat_id' => 'id']);
    }

    /**
     * Gets query for [[Participants]] through chat_participants table.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParticipants()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('chat_participants', ['chat_id' => 'id']);
    }

    /**
     * Gets active participants (not left the chat)
     *
     * @return \yii\db\ActiveQuery
     */
    public function getActiveParticipants()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('chat_participants', ['chat_id' => 'id'], function ($query) {
                $query->andWhere(['left_at' => null]);
            });
    }

    /**
     * Get last message
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLastMessage()
    {
        return $this->hasOne(ChatMessage::class, ['chat_id' => 'id'])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        $this->updated_at = time();

        if ($insert) {
            if ($this->company_id === null) {
                $this->company_id = \Yii::$app->params['company_id'] ?? null;
            }

            // Prevent saving if company_id is not valid
            if ($this->company_id === null || $this->company_id < 1) {
                return false;
            }
        }

        return parent::beforeSave($insert);
    }

    public static function find()
    {
        $query = parent::find();
        $companyId = \Yii::$app->params['company_id'] ?? null;

        if ($companyId === null || $companyId < 1) {
            $query->where('1=0');
            return $query;
        }

        $query->andWhere(['company_id' => $companyId]);
        return $query;
    }

    public function generateUuidV4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get chat icon based on type
     */
    public static function getChatIcon($type)
    {
        switch ($type) {
            case self::TYPE_COURIER:
                return 'headset';
            case self::TYPE_EMPLOYEE_PRIVATE:
                return 'user';
            case self::TYPE_EMPLOYEE_GROUP:
                return 'users';
            default:
                return 'comment';
        }
    }

    /**
     * Get default chat title
     */
    public static function getDefaultChatTitle($chat)
    {
        switch ($chat->type) {
            case self::TYPE_COURIER:
                return $chat->courier ? 'Support: ' . trim($chat->courier->first_name . ' ' . $chat->courier->last_name) : 'Support Chat';
            case self::TYPE_EMPLOYEE_PRIVATE:
                foreach ($chat->activeParticipants as $participant) {
                    if ($participant->id != Yii::$app->user->id) {
                        return trim($participant->first_name . ' ' . $participant->last_name);
                    }
                }
                return 'Private Chat';
            case self::TYPE_EMPLOYEE_GROUP:
                return $chat->title ?: 'Group Chat';
            default:
                return 'Chat';
        }
    }

}