<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "chat_message_read_status".
 *
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property int $is_read
 * @property int|null $read_at
 *
 * Relations:
 * @property ChatMessage $message
 * @property User $user
 */
class ChatMessageReadStatus extends ActiveRecord
{
    const STATUS_UNREAD = 0;
    const STATUS_READ = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chat_message_read_status';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['message_id', 'user_id'], 'required'],
            [['message_id', 'user_id', 'is_read', 'read_at'], 'integer'],
            [['is_read'], 'default', 'value' => self::STATUS_UNREAD],
            [['read_at'], 'default', 'value' => null],
            [['is_read'], 'in', 'range' => [self::STATUS_UNREAD, self::STATUS_READ]],
            [['user_id', 'message_id'], 'unique', 'targetAttribute' => ['user_id', 'message_id']],
            [['message_id'], 'exist', 'skipOnError' => true, 'targetClass' => ChatMessage::class, 'targetAttribute' => ['message_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'message_id' => 'Message ID',
            'user_id' => 'User ID',
            'is_read' => 'Is Read',
            'read_at' => 'Read At',
        ];
    }

    /**
     * Get all read status labels
     * @return array
     */
    public static function getReadStatusLabels()
    {
        return [
            self::STATUS_UNREAD => 'Unread',
            self::STATUS_READ => 'Read',
        ];
    }

    /**
     * Get current read status label
     * @return string
     */
    public function getReadStatusLabel()
    {
        $labels = self::getReadStatusLabels();
        return $labels[$this->is_read] ?? 'Unknown';
    }

    /**
     * Get read status options for dropdowns
     * @return array
     */
    public static function getReadStatusOptions()
    {
        return self::getReadStatusLabels();
    }

    /**
     * Gets query for [[Message]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMessage()
    {
        return $this->hasOne(ChatMessage::class, ['id' => 'message_id']);
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
     * Check if message is read
     * @return bool
     */
    public function isRead()
    {
        return $this->is_read == self::STATUS_READ;
    }

    /**
     * Check if message is unread
     * @return bool
     */
    public function isUnread()
    {
        return $this->is_read == self::STATUS_UNREAD;
    }

    /**
     * Mark as read
     * @return bool
     */
    public function markAsRead()
    {
        if ($this->isUnread()) {
            $this->is_read = self::STATUS_READ;
            $this->read_at = time();
            return $this->save(false);
        }
        return true;
    }

    /**
     * Mark as unread
     * @return bool
     */
    public function markAsUnread()
    {
        if ($this->isRead()) {
            $this->is_read = self::STATUS_UNREAD;
            $this->read_at = null;
            return $this->save(false);
        }
        return true;
    }

    /**
     * Get unread messages count for current user
     * @param string|null $chatId Optional specific chat ID
     * @return int
     */
    public static function getUnreadCount($chatId = null)
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return 0;
        }

        $query = static::find()
            ->where(['user_id' => $userId, 'is_read' => self::STATUS_UNREAD]);

        if ($chatId !== null) {
            $query->joinWith('message')
                ->andWhere(['chat_messages.chat_id' => $chatId]);
        }

        return $query->count();
    }

    /**
     * Mark all messages as read for user in specific chat
     * @param int $userId
     * @param string $chatId
     * @return bool
     */
    public static function markAllAsReadInChat($userId, $chatId)
    {
        $timestamp = time();

        return static::updateAll(
                [
                    'is_read' => self::STATUS_READ,
                    'read_at' => $timestamp,
                ],
                [
                    'and',
                    ['user_id' => $userId],
                    ['is_read' => self::STATUS_UNREAD],
                    ['in', 'message_id', ChatMessage::find()->select('id')->where(['chat_id' => $chatId])]
                ]
            ) !== false;
    }

    /**
     * Get read status for message by user
     * @param int $messageId
     * @param int $userId
     * @return static|null
     */
    public static function findByMessageAndUser($messageId, $userId)
    {
        return static::findOne(['message_id' => $messageId, 'user_id' => $userId]);
    }

    /**
     * Create or update read status
     * @param int $messageId
     * @param int $userId
     * @param bool $isRead
     * @return static
     */
    public static function setReadStatus($messageId, $userId, $isRead = true)
    {
        $readStatus = static::findByMessageAndUser($messageId, $userId);

        if (!$readStatus) {
            $readStatus = new static([
                'message_id' => $messageId,
                'user_id' => $userId,
            ]);
        }

        $readStatus->is_read = $isRead ? self::STATUS_READ : self::STATUS_UNREAD;
        $readStatus->read_at = $isRead ? time() : null;
        $readStatus->save();

        return $readStatus;
    }

    /**
     * Get users who read the message
     * @param int $messageId
     * @return User[]
     */
    public static function getUsersWhoReadMessage($messageId)
    {
        return User::find()
            ->joinWith('chatMessageReadStatuses')
            ->where([
                'chat_message_read_status.message_id' => $messageId,
                'chat_message_read_status.is_read' => self::STATUS_READ,
            ])
            ->all();
    }

    /**
     * Get unread messages count for current user with HR-specific logic
     * @param string|null $chatId Optional specific chat ID
     * @param bool $excludeOldCourierMessages Whether to exclude old courier messages for new HR users
     * @return int
     */
    public static function getUnreadCountWithHRLogic($chatId = null, $excludeOldCourierMessages = true)
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return 0;
        }

        $query = static::find()
            ->alias('rs')
            ->joinWith('message m')
            ->where(['rs.user_id' => $userId, 'rs.is_read' => self::STATUS_UNREAD])
            ->andWhere(['m.is_deleted' => ChatMessage::STATUS_ACTIVE]);

        if ($chatId !== null) {
            $query->andWhere(['m.chat_id' => $chatId]);
        }

        // Special handling for HR specialists and courier chats
        if ($excludeOldCourierMessages && Yii::$app->user->can('hr-specialist')) {
            // Get user's creation date or when they got HR role
            $user = User::findOne($userId);
            if ($user) {
                // For HR specialists, exclude courier messages that were created before they became HR
                // This prevents showing old courier messages as unread for new HR staff

                // Try to get HR assignment time from auth_assignment table directly
                $hrAssignmentTime = (new \yii\db\Query())
                    ->select('created_at')
                    ->from('auth_assignment')
                    ->where(['user_id' => $userId, 'item_name' => 'hr-specialist'])
                    ->scalar();

                // If no assignment time found, use user creation time as fallback
                if (!$hrAssignmentTime) {
                    $hrAssignmentTime = $user->created_at;
                }

                if ($hrAssignmentTime) {
                    $query->joinWith('message.chat c')
                        ->andWhere([
                            'or',
                            // Include all non-courier chats
                            ['!=', 'c.type', Chat::TYPE_COURIER],
                            // Include courier messages created after HR assignment
                            [
                                'and',
                                ['c.type' => Chat::TYPE_COURIER],
                                ['>=', 'm.created_at', $hrAssignmentTime]
                            ]
                        ]);
                }
            }
        }

        return $query->count();
    }

    /**
     * Create read status for HR specialists when they send messages to courier chats
     * This ensures other HR specialists see the message as unread
     * @param ChatMessage $message
     */
    public static function createReadStatusForHRCourierMessage($message)
    {
        if ($message->chat->type !== Chat::TYPE_COURIER) {
            return;
        }

        // Get all HR specialists except the sender
        $hrUsers = User::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => 'hr-specialist'])
            ->andWhere(['user.status' => User::STATUS_ACTIVE])
            ->andWhere(['!=', 'user.id', $message->sender_id])
            ->all();

        foreach ($hrUsers as $hrUser) {
            // Create unread status for other HR users
            static::setReadStatus($message->id, $hrUser->id, false);
        }
    }
}