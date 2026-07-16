<?php

namespace common\models;

use Yii;
use backend\models\User;

/**
 * This is the model class for table "companies".
 *
 * @property int $id
 * @property string $name
 * @property string|null $url
 * @property int $status
 * @property int|null $administrator_id
 * @property string $landing_url
 * @property string $landing_api_key
 * @property string|null $smtp_server
 * @property int|null $smtp_port
 * @property string|null $smtp_login
 * @property string|null $smtp_password
 * @property int $need_config_update
 * @property string|null $previous_url
 *
 * @property User $administrator
 */
class Companies extends \yii\db\ActiveRecord
{
    const STATUS_STOPPED = 0;
    const STATUS_RUNNING = 1;
    const STATUS_DEPLOYING = 2;
    const CONFIG_UPDATE_NOT_NEEDED = 0;
    const CONFIG_UPDATE_NEEDED = 1;

    // Virtual attributes for creating new administrator
    public $admin_first_name;
    public $admin_last_name;
    public $admin_email;
    public $admin_password;
    public $admin_password_confirm;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'companies';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'landing_url', 'landing_api_key'], 'required'],
            [['status', 'administrator_id', 'smtp_port'], 'integer'],
            [['name', 'url', 'smtp_server', 'smtp_login', 'smtp_password'], 'string', 'max' => 255],
            [['landing_url'], 'string', 'max' => 255],
            [['landing_url'], 'url'],
            [['landing_api_key'], 'string', 'min' => 6, 'max' => 64],
            [['landing_api_key'], 'unique'],
            [['status'], 'default', 'value' => self::STATUS_STOPPED],
            [['status'], 'in', 'range' => array_keys(self::getStatusList())],

            // Rules for new administrator creation (only on create scenario)
            [['admin_first_name', 'admin_last_name', 'admin_email', 'admin_password', 'admin_password_confirm'], 'required', 'on' => 'create'],
            [['admin_first_name', 'admin_last_name'], 'string', 'max' => 255, 'on' => 'create'],
            [['admin_email'], 'email', 'on' => 'create'],
            [['admin_email'], 'unique', 'targetClass' => User::class, 'targetAttribute' => 'email', 'message' => 'This email has already been taken.', 'on' => 'create'],
            [['admin_password'], 'string', 'min' => 6, 'on' => 'create'],
            [['admin_password_confirm'], 'compare', 'compareAttribute' => 'admin_password', 'message' => 'Passwords do not match.', 'on' => 'create'],

            [['need_config_update'], 'integer'],
            [['need_config_update'], 'default', 'value' => self::CONFIG_UPDATE_NOT_NEEDED],
            [['previous_url'], 'string', 'max' => 255],

            // Rule for update scenario
            [['administrator_id'], 'required', 'on' => 'update'],
            // SMTP validation rules
            [['smtp_port'], 'integer', 'min' => 1, 'max' => 65535],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Company Name',
            'url' => 'URL',
            'status' => 'Status',
            'administrator_id' => 'Administrator',
            'landing_url' => 'Landing URL ',
            'landing_api_key' => 'Landing API Key',
            'admin_first_name' => 'Administrator First Name',
            'admin_last_name' => 'Administrator Last Name',
            'admin_email' => 'Administrator Email',
            'admin_password' => 'Administrator Password',
            'admin_password_confirm' => 'Confirm Password',
            'smtp_server' => 'SMTP Server',
            'smtp_port' => 'SMTP Port',
            'smtp_login' => 'SMTP Login',
            'smtp_password' => 'SMTP Password',
            'need_config_update' => 'Config Update Required',
            'previous_url' => 'Previous URL',
        ];
    }

    /**
     * Get list of available statuses
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_STOPPED => 'Stopped',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_DEPLOYING => 'Deploying',
        ];
    }

    /**
     * Get human-readable status name
     * @return string
     */
    public function getStatusName()
    {
        $statusList = self::getStatusList();
        return isset($statusList[$this->status]) ? $statusList[$this->status] : 'Unknown';
    }

    /**
     * Get Bootstrap badge class for status
     * @return string
     */
    public function getStatusBadgeClass()
    {
        switch ($this->status) {
            case self::STATUS_STOPPED:
                return 'badge-secondary';
            case self::STATUS_RUNNING:
                return 'badge-success';
            case self::STATUS_DEPLOYING:
                return 'badge-warning';
            default:
                return 'badge-light';
        }
    }

    /**
     * Gets query for [[Administrator]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAdministrator()
    {
        return $this->hasOne(User::class, ['id' => 'administrator_id']);
    }

    /**
     * Get administrator full name
     * @return string|null
     */
    public function getAdministratorName()
    {
        if ($this->administrator_id && $this->administrator) {
            return trim($this->administrator->first_name . ' ' . $this->administrator->last_name);
        }
        return null;
    }

    /**
     * Generate new API key for landing page integration
     * @return string
     */
    public function generateLandingApiKey()
    {
        return Yii::$app->security->generateRandomString(32);
    }

    /**
     * Find company by landing API key
     * @param string $apiKey
     * @return Companies|null
     */
    public static function findByLandingApiKey($apiKey)
    {
        return static::findOne(['landing_api_key' => $apiKey]);
    }

    /**
     * Create deployment log
     * @param int $userId
     * @return LogsCompany|false
     */
    public function createDeploymentLog($userId)
    {
        $log = new \common\models\LogsCompany();
        $log->company_id = $this->id;
        $log->user_id = $userId;
        $log->action_type = \common\models\LogsCompany::ACTION_DEPLOY;

        if ($log->save()) {
            return $log;
        }
        return false;
    }


    /**
     * Extract domain from URL
     */
    private function getDomainFromUrl()
    {
        if ($this->landing_url) {
            $domain = preg_replace('/^https?:\/\//', '', $this->landing_url);
            $domain = preg_replace('/\/.*$/', '', $domain);
            return $domain;
        }
        return 'localhost';
    }

    /**
     * Get SMTP settings for email configuration
     * @return array
     */
    public function getSmtpSettings()
    {
        return [
            'server' => $this->smtp_server,
            'port' => $this->smtp_port,
            'login' => $this->smtp_login,
            'password' => $this->smtp_password,
        ];
    }

    /**
     * Check if SMTP is configured
     * @return bool
     */
    public function hasSmtpSettings()
    {
        return !empty($this->smtp_server) && !empty($this->smtp_login) && !empty($this->smtp_password);
    }

    /**
     * Track URL changes before save
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Track URL change for existing deployed companies
        if (!$insert && $this->isAttributeChanged('url')) {
            $oldUrl = $this->getOldAttribute('url');

            if ($oldUrl) {
                $this->previous_url = $oldUrl;
                $this->need_config_update = self::CONFIG_UPDATE_NEEDED;
            }
        }
        return true;
    }

    /**
     * Check if config update is needed
     * @return bool
     */
    public function needsConfigUpdate()
    {
        return $this->need_config_update === self::CONFIG_UPDATE_NEEDED;
    }

    /**
     * Mark config as updated (clear flag and previous_url)
     * @return bool
     */
    public function markConfigUpdated()
    {
        return $this->updateAttributes([
            'need_config_update' => self::CONFIG_UPDATE_NOT_NEEDED,
            'previous_url' => null,
        ]);
    }

    /**
     * Extract domain from any URL
     * @param string|null $url
     * @return string|null
     */
    public static function extractDomain($url)
    {
        if (empty($url)) {
            return null;
        }

        $domain = preg_replace('/^https?:\/\//', '', $url);
        $domain = preg_replace('/\/.*$/', '', $domain);
        $domain = preg_replace('/:\d+$/', '', $domain);

        return $domain ?: null;
    }
}