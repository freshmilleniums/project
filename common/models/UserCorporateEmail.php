<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use backend\models\User;

/**
 * UserCorporateEmail model
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $password
 * @property int $is_active
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class UserCorporateEmail extends ActiveRecord
{
    public $plainPassword;

    public static function tableName()
    {
        return '{{%user_corporate_emails}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['user_id', 'email'], 'required'],
            [['plainPassword'], 'string', 'min' => 8],

            [['user_id'], 'integer'],
            [['user_id'], 'unique'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],

            [['email'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['email'], 'unique'],

            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => 1],
            [['last_uid', 'last_sync_at'], 'integer'],
            [['last_uid'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'email' => 'Corporate Email',
            'plainPassword' => 'Password',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'last_uid'     => 'Last UID',
            'last_sync_at' => 'Last Sync At',
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->plainPassword) {
            $encryptionKey = Yii::$app->params['emailEncryptionKey'] ?? null;

            if ($encryptionKey === null) {
                $this->addError('plainPassword', 'Email encryption key is not configured');
                return false;
            }

            try {
                $this->password = base64_encode(
                    Yii::$app->security->encryptByPassword(
                        $this->plainPassword,
                        $encryptionKey
                    )
                );
            } catch (\Exception $e) {
                $this->addError('plainPassword', 'Failed to encrypt password');
                return false;
            }
        }

        return true;
    }

    public function getDecryptedPassword()
    {
        $encryptionKey = Yii::$app->params['emailEncryptionKey'] ?? null;

        if ($encryptionKey === null) {
            Yii::error('Email encryption key is not configured');
            return null;
        }

        try {
            return Yii::$app->security->decryptByPassword(
                base64_decode($this->password),
                $encryptionKey
            );
        } catch (\Exception $e) {
            Yii::error('Failed to decrypt password for corporate email ' . $this->id);
            return null;
        }
    }

    public function getImapSettings()
    {
        return [
            'host' => Yii::$app->params['corporateImapHost'] ?? 'imap.yourcompany.com',
            'port' => Yii::$app->params['corporateImapPort'] ?? 993,
            'encryption' => Yii::$app->params['corporateImapEncryption'] ?? 'ssl',
            'username' => $this->email,
            'password' => $this->getDecryptedPassword(),
        ];
    }

    public function getSmtpSettings()
    {
        return [
            'host' => Yii::$app->params['corporateSmtpHost'] ?? 'smtp.yourcompany.com',
            'port' => Yii::$app->params['corporateSmtpPort'] ?? 587,
            'encryption' => Yii::$app->params['corporateSmtpEncryption'] ?? 'tls',
            'username' => $this->email,
            'password' => $this->getDecryptedPassword(),
        ];
    }

    public function getImapConnectionString($folder = 'INBOX')
    {
        $settings = $this->getImapSettings();
        $encryption = $settings['encryption'] === 'ssl' ? '/ssl' : '/tls';

        return '{' . $settings['host'] . ':' . $settings['port'] . '/imap' . $encryption . '}' . $folder;
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->plainPassword = null;
    }
}