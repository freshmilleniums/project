<?php

namespace backend\models;

use Yii;
use common\services\DashboardStatisticsService;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $auth_key
 * @property string $password_hash
 * @property string|null $password_reset_token
 * @property string $email
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property string|null $verification_token
 * @property string $first_name
 * @property string $last_name
 * @property string|null $address
 * @property string|null $phone_number
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip_code
 * @property int|null $substatus
 */
class User extends \common\models\User
{
    public $attempts_count;
    public $last_attempt_at;
    // Employee substatus constants
    const SUBSTATUS_NOT_PROCESSED = 1;
    const SUBSTATUS_NEW_APPLICANT = 2;
    const SUBSTATUS_PRIMARY_CONTACT = 3;
    const SUBSTATUS_INTERVIEW_COMPLETED = 4;
    const SUBSTATUS_CONTRACT_SENT = 5;
    const SUBSTATUS_TRAINING_IN_PROGRESS = 6;
    const SUBSTATUS_FINAL_ASSIGNMENT = 7;
    const SUBSTATUS_ACTIVE_EMPLOYEE = 8;
    const SUBSTATUS_CONTRACT_REFUSED = 9;
    const SUBSTATUS_ARCHIVED = 10;
    const SUBSTATUS_UNCOMPLETED_TASK = 11;

    //  const SIGN_STYLE_DANCING_SCRIPT = 'Dancing Script';
    const SIGN_STYLE_PACIFICO = 'Pacifico';
    const SIGN_STYLE_GREAT_VIBES = 'Great Vibes';
    //   const SIGN_STYLE_SATISFY = 'Satisfy';
    const SIGN_STYLE_ALLURA = 'Allura';
    const SIGN_STYLE_ALEX_BRUSH = 'Alex Brush';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['password_reset_token', 'verification_token', 'hr_source', 'sequential_number', 'current_project_id',
                'administrator_id'], 'default', 'value' => null],
            [['total_time_today', 'total_time_all'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['substatus'], 'default', 'value' => self::SUBSTATUS_NOT_PROCESSED],
            [['is_online'], 'default', 'value' => 0],
            [['company_id'], 'default', 'value' => function() {
                if (in_array($this->role, ['super-administrator', 'administrator'])) {
                    return 0;
                }
                return Yii::$app->params['company_id'] ?? null;
            }],

            [['auth_key', 'password_hash', 'email', 'created_at', 'updated_at', 'first_name', 'last_name', 'phone_number',
                'home_phone', 'address', 'city', 'state', 'country', 'zip_code', 'position_title'], 'required'],

            [['status', 'created_at', 'updated_at', 'substatus', 'substatus_changed_at', 'last_activity', 'sequential_number',
                'current_project_id', 'administrator_id', 'total_time_today', 'total_time_all', 'company_id'], 'integer'],
            [['auth_key'], 'string', 'max' => 32],
            [['password_hash', 'password_reset_token', 'email', 'verification_token', 'first_name', 'last_name',
                'position_title', 'hr_source'], 'string', 'max' => 255],
            [['phone_number', 'home_phone'], 'string', 'max' => 20],
            [['address'], 'string', 'max' => 500],
            [['city', 'country'], 'string', 'max' => 100],
            [['state'], 'string', 'max' => 100],
            [['zip_code'], 'string', 'max' => 20],
            [['contract_pdf_path'], 'string', 'max' => 500],
            [['sign_signature_text'], 'string', 'max' => 255],
            [['email'], 'unique'],
            [['password_reset_token'], 'unique'],
            [['sequential_number'], 'unique'],
            [['email'], 'email'],
            [['is_online'], 'boolean'],
            [['substatus'], 'in', 'range' => array_keys(self::getSubstatusLabels())],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            ['role', 'in', 'range' => array_keys(Yii::$app->authManager->getRoles())],

            [['sign_signature_style', 'sign_signature_text'], 'required', 'on' => 'sign_contract'],
            [['sign_signature_style'], 'in', 'range' => array_keys(self::getSignatureStyles()), 'on' => 'sign_contract'],
            [['call_center_operator_id'], 'integer'],
            [['call_center_operator_id'], 'default', 'value' => null],
            [['call_center_operator_id'], 'exist', 'skipOnError' => true,
                'targetClass' => self::class, 'targetAttribute' => ['call_center_operator_id' => 'id']],

        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['sign_contract'] = ['sign_signature_style', 'sign_signature_text'];
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone_number' => 'Phone Number',
            'home_phone' => 'Home Phone',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State/Region',
            'country' => 'Country',
            'zip_code' => 'Zip Code',
            'status' => 'Status Main',
            'substatus' => 'Status',
            'sequential_number' => 'Sequential Number',
            'position_title' => 'Position Title',
            'current_project_id' => 'Current Project',
            'hr_source' => 'HR Source',
            'administrator_id' => 'Administrator',
            'total_time_today' => 'Total Time Today',
            'is_online' => 'Is Online',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'substatus_changed_at' => 'Substatus Changed At',
            'last_activity' => 'Last Activity',
            'total_time_all' => 'Total Time Online',
            'call_center_operator_id' => 'Call Center Operator',
        ];
    }

    /**
     * Get all substatus labels
     * @return array
     */
    public static function getSubstatusLabels()
    {
        return [
            self::SUBSTATUS_NOT_PROCESSED => 'Not Processed',
            self::SUBSTATUS_NEW_APPLICANT => 'New Applicant',
            self::SUBSTATUS_PRIMARY_CONTACT => 'Primary Contact',
            self::SUBSTATUS_INTERVIEW_COMPLETED => 'Interview Completed',
            self::SUBSTATUS_CONTRACT_SENT => 'Contract Sent',
            self::SUBSTATUS_TRAINING_IN_PROGRESS => 'Training In Progress',
            self::SUBSTATUS_FINAL_ASSIGNMENT => 'Final Assignment',
            self::SUBSTATUS_ACTIVE_EMPLOYEE => 'Active Employee',
            self::SUBSTATUS_CONTRACT_REFUSED => 'Contract Refused',
            self::SUBSTATUS_ARCHIVED => 'Archived',
            self::SUBSTATUS_UNCOMPLETED_TASK => 'Uncompleted Task',
        ];
    }

    /**
     * Get filtered substatus labels for specific courier group
     * @param string|null $groupKey - group key from UserService::COURIER_STATUS_GROUPS
     * @return array
     */
    public static function getFilteredSubstatusLabels($groupKey = null)
    {
        $allLabels = self::getSubstatusLabels();

        if ($groupKey === null) {
            return $allLabels;
        }

        // Get service to access group constants
        $userService = new \common\services\UserService();
        $allowedSubstatuses = $userService->getAllowedSubstatusesForGroup($groupKey);

        if (empty($allowedSubstatuses)) {
            return $allLabels;
        }

        return array_intersect_key($allLabels, array_flip($allowedSubstatuses));
    }

    public static function getSignatureStyles()
    {
        return [
            // self::SIGN_STYLE_DANCING_SCRIPT => 'Dancing Script',
            self::SIGN_STYLE_PACIFICO => 'Pacifico',
            self::SIGN_STYLE_GREAT_VIBES => 'Great Vibes',
            //  self::SIGN_STYLE_SATISFY => 'Satisfy',
            self::SIGN_STYLE_ALLURA => 'Allura',
            self::SIGN_STYLE_ALEX_BRUSH => 'Alex Brush',
        ];
    }

    /**
     * Get current substatus label
     * @return string
     */
    public function getSubstatusLabel()
    {
        $labels = self::getSubstatusLabels();
        return $labels[$this->substatus] ?? 'Unknown';
    }

    /**
     * Get substatus options for dropdowns
     * @return array
     */
    public static function getSubstatusOptions()
    {
        return self::getSubstatusLabels();
    }

    /**
     * Handle statistics tracking after model is saved
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get administrator relation
     * @return \yii\db\ActiveQuery
     */
    public function getAdministrator()
    {
        return $this->hasOne(self::class, ['id' => 'administrator_id']);
    }

    /**
     * Check if user is online based on last activity
     * @return bool
     */
    public function isOnline(): bool
    {
        if (!$this->last_activity) {
            return false;
        }
        return (time() - $this->last_activity) < 5 * 60;
    }

    /**
     * Update last activity timestamp
     * @return bool
     */
    public function updateLastActivity()
    {
        $this->last_activity = time();
        return $this->save(false, ['last_activity']);
    }

    /**
     * Set user online status
     * @param bool $online
     * @return bool
     */
    public function setOnlineStatus($online = true)
    {
        $this->is_online = $online ? 1 : 0;
        return $this->save(false, ['is_online']);
    }

    /**
     * Before save hook
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Auto-generate sequential_number if not set
        if ($insert && empty($this->sequential_number)) {
            $maxNumber = self::find()->max('sequential_number');
            $this->sequential_number = ($maxNumber ?? 0) + 1;
        }

        return true;
    }

    /**
     * Get list of administrators for dropdown
     * @return array
     */
    public static function getAdministratorsList()
    {
        return self::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => ['administrator']])
            ->select(['user.id', 'user.first_name', 'user.last_name'])
            ->asArray()
            ->all();
    }

    /**
     * Get list of employees for dropdown
     * @param int|null $administratorId - filter by administrator (optional)
     * @return array [id => 'Full Name']
     */
    public static function getEmployeesDropdownList($administratorId = null)
    {
        $query = self::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => 'employee'])
            ->andWhere(['user.status' => self::STATUS_ACTIVE]);

        if ($administratorId !== null) {
            $query->andWhere(['user.administrator_id' => $administratorId]);
        }

        $employees = $query
            ->select(["CONCAT(user.first_name, ' ', user.last_name) AS full_name", 'user.id'])
            ->asArray()
            ->all();

        return \yii\helpers\ArrayHelper::map($employees, 'id', 'full_name');
    }

    /**
     * Get list of administrators for dropdown
     * @return array [id => 'Full Name']
     */
    public static function getAdministratorsDropdownList()
    {
        $administrators = self::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => 'administrator'])
            ->andWhere(['user.status' => self::STATUS_ACTIVE])
            ->select(["CONCAT(user.first_name, ' ', user.last_name) AS full_name", 'user.id'])
            ->asArray()
            ->all();

        return \yii\helpers\ArrayHelper::map($administrators, 'id', 'full_name');
    }

    /**
     * Get allowed status transitions for phone-operator
     * based on current employee substatus
     * @return array [substatus => label]
     */
    public function getAllowedStatusTransitionsForPhoneOperator(): array
    {
        $all = self::getSubstatusLabels();

        $transitions = [
            self::SUBSTATUS_NOT_PROCESSED        => [self::SUBSTATUS_NEW_APPLICANT, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_NEW_APPLICANT        => [self::SUBSTATUS_PRIMARY_CONTACT, self::SUBSTATUS_CONTRACT_REFUSED, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_PRIMARY_CONTACT      => [self::SUBSTATUS_INTERVIEW_COMPLETED, self::SUBSTATUS_CONTRACT_REFUSED, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_INTERVIEW_COMPLETED  => [self::SUBSTATUS_CONTRACT_SENT, self::SUBSTATUS_CONTRACT_REFUSED, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_CONTRACT_SENT        => [self::SUBSTATUS_TRAINING_IN_PROGRESS, self::SUBSTATUS_CONTRACT_REFUSED, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_TRAINING_IN_PROGRESS => [self::SUBSTATUS_FINAL_ASSIGNMENT, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_FINAL_ASSIGNMENT     => [self::SUBSTATUS_ACTIVE_EMPLOYEE, self::SUBSTATUS_UNCOMPLETED_TASK, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_UNCOMPLETED_TASK     => [self::SUBSTATUS_FINAL_ASSIGNMENT, self::SUBSTATUS_ARCHIVED],
            self::SUBSTATUS_ACTIVE_EMPLOYEE      => [self::SUBSTATUS_ARCHIVED],
        ];

        $allowed = $transitions[$this->substatus] ?? [];

        return array_intersect_key($all, array_flip($allowed));
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDocuments()
    {
        return $this->hasMany(\common\models\UserDocument::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSentTemplates()
    {
        return $this->hasMany(\common\models\UserSentTemplate::class, ['user_id' => 'id'])
            ->orderBy(['sent_at' => SORT_ASC]);
    }
    /**
     * Format seconds to human readable string
     * @param int $seconds
     * @return string
     */
    public static function formatSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 minutes';
        }

        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($hours > 0)   $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');

        return $parts ? implode(', ', $parts) : 'Less than a minute';
    }

    /**
     * Get total time online formatted
     * @return string
     */
    public function getTotalTimeAllFormatted(): string
    {
        return self::formatSeconds($this->total_time_all ?? 0);
    }

    /**
     * Get today time online formatted
     * @return string
     */
    public function getTotalTimeTodayFormatted(): string
    {
        return self::formatSeconds($this->total_time_today ?? 0);
    }

    /**
     * Get offline duration formatted
     * @return string
     */
    public function getOfflineDurationFormatted(): string
    {
        if ($this->is_online || !$this->last_activity) {
            return '';
        }

        return self::formatSeconds(time() - $this->last_activity);
    }

    public function getCallCenterOperator()
    {
        return $this->hasOne(self::class, ['id' => 'call_center_operator_id']);
    }

    public function getAssignedCandidates()
    {
        return $this->hasMany(self::class, ['call_center_operator_id' => 'id']);
    }

    /**
     * Get list of phone operators for dropdown
     * @return array
     */
    public static function getPhoneOperatorsList()
    {
        return self::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => ['phone-operator']])
            ->andWhere(['user.status' => self::STATUS_ACTIVE])
            ->select(['user.id', 'user.first_name', 'user.last_name'])
            ->asArray()
            ->all();
    }

}