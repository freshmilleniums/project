<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;

/**
 * This is the model class for table "chat_message_attachments".
 *
 * @property int $id
 * @property int $message_id
 * @property string $original_name
 * @property string $stored_name
 * @property string $file_path
 * @property int $file_size
 * @property string $file_type
 * @property int $created_at
 *
 * Relations:
 * @property ChatMessage $message
 */
class ChatMessageAttachment extends ActiveRecord
{
    /**
     * @var UploadedFile[]
     */
    public $files;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'chat_message_attachments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['message_id', 'original_name', 'stored_name', 'file_path', 'file_size', 'file_type'], 'required'],
            [['message_id', 'file_size', 'created_at'], 'integer'],
            [['original_name', 'stored_name'], 'string', 'max' => 255],
            [['file_path'], 'string', 'max' => 500],
            [['file_type'], 'string', 'max' => 100],
            [['created_at'], 'default', 'value' => function() { return time(); }],
            [['message_id'], 'exist', 'skipOnError' => true, 'targetClass' => ChatMessage::class, 'targetAttribute' => ['message_id' => 'id']],
            [['files'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, pdf, doc, docx, odt', 'maxFiles' => 10, 'maxSize' => 10 * 1024 * 1024], // 10MB max per file
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
            'original_name' => 'Original Name',
            'stored_name' => 'Stored Name',
            'file_path' => 'File Path',
            'file_size' => 'File Size',
            'file_type' => 'File Type',
            'created_at' => 'Created At',
            'files' => 'Attachments',
        ];
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
     * Get file URL for download/viewing
     * @return string
     */
    public function getUrl()
    {
        return Yii::$app->urlManager->baseUrl . '/uploads/' . $this->file_path;
    }

    /**
     * Get full file system path
     * @return string
     */
    public function getFilePath()
    {
        if (Yii::$app->id == 'app-api') {
            $uploadPath = realpath(dirname(__FILE__) . '/../../') . '/backend/web/uploads/';
        } else {
            $uploadPath = Yii::$app->basePath . '/web/uploads/';
        }

        return $uploadPath . $this->file_path;
    }

    /**
     * Check if attachment is image
     * @return bool
     */
    public function isImage()
    {
        return strpos($this->file_type, 'image/') === 0;
    }

    /**
     * Check if attachment is PDF
     * @return bool
     */
    public function isPdf()
    {
        return $this->file_type === 'application/pdf';
    }

    /**
     * Check if attachment is document (Word, ODT, etc.)
     * @return bool
     */
    public function isDocument()
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
            'text/plain'
        ];
        return in_array($this->file_type, $documentTypes);
    }

    /**
     * Get human readable file size
     * @return string
     */
    public function getFormattedFileSize()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * Get file icon class based on file type
     * @return string
     */
    public function getFileIcon()
    {
        if ($this->isImage()) {
            return 'fas fa-image';
        } elseif ($this->isPdf()) {
            return 'fas fa-file-pdf';
        } elseif ($this->isDocument()) {
            return 'fas fa-file-word';
        } else {
            return 'fas fa-file';
        }
    }

    /**
     * Upload multiple files and create attachment records
     * @param ChatMessage $message
     * @param UploadedFile[] $files
     * @return array Array of created attachment records
     */
    public static function uploadFiles($message, $files)
    {
        $attachments = [];

        if (empty($files)) {
            return $attachments;
        }

        foreach ($files as $file) {
            $attachment = new static();
            $attachment->message_id = $message->id;

            if ($attachment->uploadSingleFile($file)) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * Upload single file
     * @param UploadedFile $file
     * @return bool
     */
    public function uploadSingleFile($file)
    {
        if (!$file) {
            return false;
        }

        // Generate unique file name
        $pathInfo = pathinfo($file->name);
        $ext = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        $fileNewName = md5($file->name . time() . rand(1000, 9999));
        $fileDir = 'chatAttachments/' . date('Y') . '/' . date('m') . '/' . date('d') . '/';

        // Get upload path
        if (Yii::$app->id == 'app-api') {
            $uploadPath = realpath(dirname(__FILE__) . '/../../') . '/backend/web/uploads/';
        } else {
            $uploadPath = Yii::$app->basePath . '/web/uploads/';
        }

        $path = $uploadPath . $fileDir;

        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            FileHelper::createDirectory($path);
        }

        // Set file properties
        $this->original_name = $file->name;
        $this->stored_name = time() . '_' . $fileNewName . '.' . $ext;
        $this->file_path = $fileDir . $this->stored_name;
        $this->file_size = $file->size;
        $this->file_type = $file->type;
        $this->created_at = time();

        // Save file and record
        if ($file->saveAs($this->getFilePath()) && $this->save()) {
            return true;
        }

        return false;
    }

    /**
     * Delete file from filesystem
     * @return bool
     */
    public function deleteFile()
    {
        $filePath = $this->getFilePath();

        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }

        return true;
    }

    /**
     * Get attachment preview data for frontend
     * @return array
     */
    public function getPreviewData()
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'file_size' => $this->getFormattedFileSize(),
            'file_type' => $this->file_type,
            'icon' => $this->getFileIcon(),
            'url' => $this->getUrl(),
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),
            'is_document' => $this->isDocument(),
            'created_at' => Yii::$app->formatter->asDatetime($this->created_at),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        $this->deleteFile();
        return parent::beforeDelete();
    }

    /**
     * Get attachments for a specific message
     * @param int $messageId
     * @return static[]
     */
    public static function getMessageAttachments($messageId)
    {
        return static::find()
            ->where(['message_id' => $messageId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();
    }
}