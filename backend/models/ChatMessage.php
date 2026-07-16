<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;

/**
 * This is the model class for table "chat_messages".
 *
 * @property int $id
 * @property string $chat_id
 * @property int $sender_id
 * @property string $message_text
 * @property int $message_type
 * @property int|null $reply_to_message_id
 * @property int $created_at
 * @property int|null $updated_at
 * @property int $is_deleted
 *
 * Relations:
 * @property Chat $chat
 * @property User $sender
 * @property ChatMessage|null $replyToMessage
 * @property ChatMessage[] $replies
 * @property ChatMessageAttachment[] $attachments
 * @property ChatMessageReadStatus[] $chatMessageReadStatuses
 * @property User[] $readByUsers
 */
class ChatMessage extends ActiveRecord
{
    const MESSAGE_TYPE_TEXT = 0;
    const MESSAGE_TYPE_IMAGE = 1;
    const MESSAGE_TYPE_FILE = 2;
    const MESSAGE_TYPE_SYSTEM = 3;

    const STATUS_ACTIVE = 0;
    const STATUS_DELETED = 1;

    /**
     * @var UploadedFile[]
     */
    public $attachmentFiles;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chat_messages';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['chat_id', 'sender_id'], 'required'],
            [['sender_id', 'message_type', 'reply_to_message_id', 'created_at', 'updated_at', 'is_deleted'], 'integer'],
            [['message_text'], 'string'],
            [['created_at'], 'default', 'value' => time()],
            [['chat_id'], 'string', 'max' => 36],
            [['message_type'], 'default', 'value' => self::MESSAGE_TYPE_TEXT],
            [['is_deleted'], 'default', 'value' => self::STATUS_ACTIVE],
            [['updated_at', 'reply_to_message_id'], 'default', 'value' => null],
            [['message_type'], 'in', 'range' => [
                self::MESSAGE_TYPE_TEXT,
                self::MESSAGE_TYPE_IMAGE,
                self::MESSAGE_TYPE_FILE,
                self::MESSAGE_TYPE_SYSTEM,
            ]],
            [['is_deleted'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
            [['chat_id'], 'exist', 'skipOnError' => true, 'targetClass' => Chat::class, 'targetAttribute' => ['chat_id' => 'id']],
            [['sender_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['sender_id' => 'id']],
            [['reply_to_message_id'], 'exist', 'skipOnError' => true, 'targetClass' => self::class, 'targetAttribute' => ['reply_to_message_id' => 'id']],
            [['attachmentFiles'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, pdf, doc, docx, odt', 'maxFiles' => 10, 'maxSize' => 10 * 1024 * 1024], // 10MB max per file
            // Custom validation: either message_text or attachments must be present
            ['message_text', 'validateMessageContent'],
        ];
    }

    /**
     * Custom validator to ensure either text or attachments are present
     * @param string $attribute
     * @param array $params
     */
    public function validateMessageContent($attribute, $params)
    {
        if (empty($this->message_text) && empty($this->attachmentFiles) && empty($this->attachments)) {
            $this->addError($attribute, 'Message must contain either text or attachments.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'chat_id' => 'Chat ID',
            'sender_id' => 'Sender ID',
            'message_text' => 'Message Text',
            'message_type' => 'Message Type',
            'reply_to_message_id' => 'Reply To Message ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'is_deleted' => 'Is Deleted',
            'attachmentFiles' => 'Attachments',
        ];
    }

    /**
     * Get all message type labels
     * @return array
     */
    public static function getMessageTypeLabels()
    {
        return [
            self::MESSAGE_TYPE_TEXT => 'Text',
            self::MESSAGE_TYPE_IMAGE => 'Image',
            self::MESSAGE_TYPE_FILE => 'File',
            self::MESSAGE_TYPE_SYSTEM => 'System',
        ];
    }

    /**
     * Get current message type label
     * @return string
     */
    public function getMessageTypeLabel()
    {
        $labels = self::getMessageTypeLabels();
        return $labels[$this->message_type] ?? 'Unknown';
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
     * Gets query for [[Sender]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSender()
    {
        return $this->hasOne(User::class, ['id' => 'sender_id']);
    }

    /**
     * Gets query for [[ReplyToMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReplyToMessage()
    {
        return $this->hasOne(self::class, ['id' => 'reply_to_message_id']);
    }

    /**
     * Gets query for [[Replies]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReplies()
    {
        return $this->hasMany(self::class, ['reply_to_message_id' => 'id']);
    }

    /**
     * Gets query for [[Attachments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAttachments()
    {
        return $this->hasMany(ChatMessageAttachment::class, ['message_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    /**
     * Gets query for [[ChatMessageReadStatuses]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChatMessageReadStatuses()
    {
        return $this->hasMany(ChatMessageReadStatus::class, ['message_id' => 'id']);
    }

    /**
     * Gets query for [[ReadByUsers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getReadByUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('chat_message_read_status', ['message_id' => 'id'], function ($query) {
                $query->andWhere(['is_read' => 1]);
            });
    }

    /**
     * Check if message is deleted
     * @return bool
     */
    public function isDeleted()
    {
        return $this->is_deleted == self::STATUS_DELETED;
    }

    /**
     * Check if message is active
     * @return bool
     */
    public function isActive()
    {
        return $this->is_deleted == self::STATUS_ACTIVE;
    }

    /**
     * Check if message has been edited
     * @return bool
     */
    public function isEdited()
    {
        return !empty($this->updated_at) && $this->updated_at != $this->created_at;
    }

    /**
     * Check if message has attachments
     * @return bool
     */
    public function hasAttachments()
    {
        return count($this->attachments) > 0;
    }

    /**
     * Check if message is a reply
     * @return bool
     */
    public function isReply()
    {
        return !empty($this->reply_to_message_id);
    }

    /**
     * Get count of attachments
     * @return int
     */
    public function getAttachmentsCount()
    {
        return count($this->attachments);
    }

    /**
     * Get first attachment (for preview)
     * @return ChatMessageAttachment|null
     */
    public function getFirstAttachment()
    {
        return !empty($this->attachments) ? $this->attachments[0] : null;
    }

    /**
     * Soft delete the message
     * @return bool
     */
    public function softDelete()
    {
        $this->is_deleted = self::STATUS_DELETED;
        $this->updated_at = time();
        return $this->save(false);
    }

    /**
     * Restore deleted message
     * @return bool
     */
    public function restore()
    {
        if ($this->isDeleted()) {
            $this->is_deleted = self::STATUS_ACTIVE;
            $this->updated_at = time();
            return $this->save(false);
        }
        return true;
    }

    /**
     * Edit message text
     * @param string $newText
     * @return bool
     */
    public function editMessage($newText)
    {
        $this->message_text = $newText;
        $this->updated_at = time();
        return $this->save(false);
    }

    /**
     * Check if message can be edited by user
     * @param int $userId
     * @return bool
     */
    public function canBeEditedBy($userId)
    {
        // Only sender can edit their own messages
        if ($this->sender_id != $userId) {
            return false;
        }

        // Can't edit deleted messages
        if ($this->isDeleted()) {
            return false;
        }

        // Can't edit system messages
        if ($this->message_type == self::MESSAGE_TYPE_SYSTEM) {
            return false;
        }

        // Allow editing within 24 hours
        $editTimeLimit = 24 * 60 * 60; // 24 hours
        if (time() - $this->created_at > $editTimeLimit) {
            return false;
        }

        return true;
    }

    /**
     * Check if message is read by specific user
     * @param int $userId
     * @return bool
     */
    public function isReadByUser($userId)
    {
        return ChatMessageReadStatus::find()
            ->where(['message_id' => $this->id, 'user_id' => $userId, 'is_read' => 1])
            ->exists();
    }

    /**
     * Mark message as read by user
     * @param int $userId
     * @return bool
     */
    public function markAsReadByUser($userId)
    {
        $readStatus = ChatMessageReadStatus::findOne(['message_id' => $this->id, 'user_id' => $userId]);
        if (!$readStatus) {
            $readStatus = new ChatMessageReadStatus([
                'message_id' => $this->id,
                'user_id' => $userId,
            ]);
        }

        if (!$readStatus->is_read) {
            $readStatus->is_read = 1;
            $readStatus->read_at = time();
            return $readStatus->save();
        }

        return true;
    }

    /**
     * Upload attachments
     * @return bool
     */
    public function uploadAttachments()
    {
        if (empty($this->attachmentFiles)) {
            return true;
        }

        $attachments = ChatMessageAttachment::uploadFiles($this, $this->attachmentFiles);

        // Update message type if attachments were uploaded
        if (!empty($attachments)) {
            // Determine message type based on attachments
            $hasImages = false;
            $hasFiles = false;

            foreach ($attachments as $attachment) {
                if ($attachment->isImage()) {
                    $hasImages = true;
                } else {
                    $hasFiles = true;
                }
            }

            // Set message type: if mixed or only files, use FILE; if only images, use IMAGE
            if ($hasFiles || ($hasImages && $hasFiles)) {
                $this->message_type = self::MESSAGE_TYPE_FILE;
            } elseif ($hasImages) {
                $this->message_type = self::MESSAGE_TYPE_IMAGE;
            }

            return $this->save(false);
        }

        return true;
    }

    /**
     * Get formatted message data for API/AJAX responses
     * @param int $currentUserId
     * @return array
     */
    public function getFormattedData($currentUserId)
    {
        $data = [
            'id' => $this->id,
            'text' => $this->message_text,
            'sender_name' => $this->sender ?
                trim($this->sender->first_name . ' ' . $this->sender->last_name) : 'Unknown',
            'sender_id' => $this->sender_id,
            'created_at' => Yii::$app->formatter->asDatetime($this->created_at),
            'created_at_timestamp' => $this->created_at,
            'message_type' => $this->message_type,
            'is_own' => $this->sender_id == $currentUserId,
            'is_edited' => $this->isEdited(),
            'can_edit' => $this->canBeEditedBy($currentUserId),
            'has_attachments' => $this->hasAttachments(),
            'attachments_count' => $this->getAttachmentsCount(),
        ];

        // Add edited information if message was edited
        if ($this->isEdited()) {
            $data['edited_at'] = Yii::$app->formatter->asDatetime($this->updated_at);
            $data['edited_at_timestamp'] = $this->updated_at;
        }

        // Add reply information if this is a reply
        if ($this->isReply() && $this->replyToMessage) {
            $data['reply_to'] = [
                'id' => $this->replyToMessage->id,
                'text' => mb_substr($this->replyToMessage->message_text, 0, 100) .
                    (mb_strlen($this->replyToMessage->message_text) > 100 ? '...' : ''),
                'sender_name' => $this->replyToMessage->sender ?
                    trim($this->replyToMessage->sender->first_name . ' ' . $this->replyToMessage->sender->last_name) : 'Unknown',
                'has_attachments' => $this->replyToMessage->hasAttachments(),
            ];
        }

        // Add attachments data
        if ($this->hasAttachments()) {
            $data['attachments'] = [];
            foreach ($this->attachments as $attachment) {
                $data['attachments'][] = $attachment->getPreviewData();
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            // Upload attachments after saving the message
            $this->uploadAttachments();

            // Update chat's last_message_at when new message is created
            $this->chat->last_message_at = $this->created_at;
            $this->chat->save(false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        // Delete all attachments before deleting the message
        foreach ($this->attachments as $attachment) {
            $attachment->delete();
        }

        return parent::beforeDelete();
    }

    /**
     * Get messages for a specific chat with relations
     * @param string $chatId
     * @param int $limit
     * @param int $offset
     * @return static[]
     */
    public static function getChatMessages($chatId, $limit = 50, $offset = 0)
    {
        return static::find()
            ->where(['chat_id' => $chatId, 'is_deleted' => self::STATUS_ACTIVE])
            ->with(['sender', 'replyToMessage', 'replyToMessage.sender', 'attachments'])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();
    }
}