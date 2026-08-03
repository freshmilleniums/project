<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * EmailAccount model
 *
 * @property int $id
 * @property string $email
 * @property string|null $label
 * @property string $username
 * @property string $password
 * @property string $imap_host
 * @property int $imap_port
 * @property int $imap_encryption
 * @property string $smtp_host
 * @property int $smtp_port
 * @property int $smtp_encryption
 * @property int $is_corporate
 * @property int $is_active
 * @property int $created_at
 * @property int $updated_at
 */
class EmailAccount extends ActiveRecord
{
    const IMAP_ENCRYPTION_SSL = 1;
    const IMAP_ENCRYPTION_TLS = 2;

    const SMTP_ENCRYPTION_SSL = 1;
    const SMTP_ENCRYPTION_TLS = 2;
    const SMTP_ENCRYPTION_STARTTLS = 3;

    public $plainPassword;

    public static function tableName()
    {
        return 'email_accounts';
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
            [['email', 'username', 'imap_host', 'smtp_host'], 'required'],
            [['plainPassword'], 'required', 'on' => 'create'],
            [['plainPassword'], 'string'],

            [['email', 'username', 'label', 'imap_host', 'smtp_host'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['email'], 'unique'],

            [['imap_port', 'smtp_port'], 'integer', 'min' => 1, 'max' => 65535],
            [['imap_port'], 'default', 'value' => 993],
            [['smtp_port'], 'default', 'value' => 587],

            [['imap_encryption'], 'in', 'range' => array_keys(self::getImapEncryptionList())],
            [['imap_encryption'], 'default', 'value' => self::IMAP_ENCRYPTION_SSL],

            [['smtp_encryption'], 'in', 'range' => array_keys(self::getSmtpEncryptionList())],
            [['smtp_encryption'], 'default', 'value' => self::SMTP_ENCRYPTION_TLS],

            [['is_corporate', 'is_active'], 'boolean'],
            [['is_corporate'], 'default', 'value' => 0],
            [['is_active'], 'default', 'value' => 1],
            [['last_uid', 'last_sync_at'], 'integer'],
            [['last_uid'], 'default', 'value' => 0],
            [['company_id'], 'integer'],
            [['company_id'], 'default', 'value' => function() {
                return \Yii::$app->params['company_id'] ?? null;
            }],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'email' => 'Email Address',
            'label' => 'Display Label',
            'username' => 'Username',
            'password' => 'Password',
            'plainPassword' => 'Password',
            'imap_host' => 'IMAP Host',
            'imap_port' => 'IMAP Port',
            'imap_encryption' => 'IMAP Encryption',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_encryption' => 'SMTP Encryption',
            'is_corporate' => 'Available for Employees',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'last_uid'     => 'Last UID',
            'last_sync_at' => 'Last Sync At',
        ];
    }

    public static function getImapEncryptionList()
    {
        return [
            self::IMAP_ENCRYPTION_SSL => 'SSL',
            self::IMAP_ENCRYPTION_TLS => 'TLS',
        ];
    }

    public static function getSmtpEncryptionList()
    {
        return [
            self::SMTP_ENCRYPTION_SSL => 'SSL',
            self::SMTP_ENCRYPTION_TLS => 'TLS',
            self::SMTP_ENCRYPTION_STARTTLS => 'STARTTLS',
        ];
    }

    public function getImapEncryptionName()
    {
        $list = self::getImapEncryptionList();
        return $list[$this->imap_encryption] ?? 'Unknown';
    }

    public function getSmtpEncryptionName()
    {
        $list = self::getSmtpEncryptionList();
        return $list[$this->smtp_encryption] ?? 'Unknown';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            if ($this->company_id === null) {
                $this->company_id = \Yii::$app->params['company_id'] ?? null;
            }
            if ($this->company_id === null || $this->company_id < 1) {
                return false;
            }
        }

        if ($this->plainPassword) {
            $encryptionKey = $this->getEncryptionKey();

            if ($encryptionKey === null) {
                $this->addError('plainPassword', 'Email encryption key is not configured. Please set "emailEncryptionKey" parameter in common/config/params-local.php');
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
                $this->addError('plainPassword', 'Failed to encrypt password: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    public function getDecryptedPassword()
    {
        $encryptionKey = $this->getEncryptionKey();

        if ($encryptionKey === null) {
            Yii::error('Email encryption key is not configured. Cannot decrypt password for email account ' . $this->id);
            return null;
        }

        try {
            return Yii::$app->security->decryptByPassword(
                base64_decode($this->password),
                $encryptionKey
            );
        } catch (\Exception $e) {
            Yii::error('Failed to decrypt password for email account ' . $this->id . ': ' . $e->getMessage());
            return null;
        }
    }

    protected function getEncryptionKey()
    {
        $key = Yii::$app->params['emailEncryptionKey'] ?? null;

        if (empty($key)) {
            return null;
        }

        return $key;
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->plainPassword = null;
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
}