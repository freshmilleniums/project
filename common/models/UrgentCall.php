<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "urgent_call".
 *
 * @property int $id
 * @property int $user_id
 * @property int $created_at
 * @property int $status
 * @property int|null $hr_employee_id
 * @property string|null $hr_comment
 * @property int|null $call_center_employee_id
 * @property string|null $call_center_comment
 * @property int $is_confirmed
 * @property int|null $confirmed_at
 * @property int|null $confirmed_by
 *
 * @property User $user Courier
 * @property User $hrEmployee HR employee who created the call
 * @property User $callCenterEmployee Call center employee who handled the call
 * @property User $confirmedBy HR employee who confirmed the call
 */
class UrgentCall extends ActiveRecord
{
    const STATUS_NEED_TO_CALL = 0;
    const STATUS_CALLED = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%urgent_call}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'created_at', 'status', 'hr_employee_id', 'call_center_employee_id', 'is_confirmed', 'confirmed_at', 'confirmed_by'], 'integer'],
            [['hr_comment', 'call_center_comment'], 'string', 'max' => 1000],
            [['status'], 'default', 'value' => self::STATUS_NEED_TO_CALL],
            [['status'], 'in', 'range' => [self::STATUS_NEED_TO_CALL, self::STATUS_CALLED]],
            [['is_confirmed'], 'default', 'value' => 0],
            [['is_confirmed'], 'in', 'range' => [0, 1]],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['hr_employee_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['hr_employee_id' => 'id']],
            [['call_center_employee_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['call_center_employee_id' => 'id']],
            [['confirmed_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['confirmed_by' => 'id']],
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
            'user_id' => 'Courier',
            'created_at' => 'Created At',
            'status' => 'Status',
            'hr_employee_id' => 'Created by HR',
            'hr_comment' => 'HR Comment',
            'call_center_employee_id' => 'Handled by Call Center',
            'call_center_comment' => 'Call Center Comment',
            'is_confirmed' => 'Is Confirmed',
            'confirmed_at' => 'Confirmed At',
            'confirmed_by' => 'Confirmed by HR',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            if ($this->company_id === null) {
                $this->company_id = \Yii::$app->params['company_id'] ?? null;
            }

            // Prevent saving if company_id is not valid
            if ($this->company_id === null || $this->company_id < 1) {
                return false;
            }
        }

        // Update confirmed_at if is_confirmed changes to 1
        if ($this->isAttributeChanged('is_confirmed') && $this->is_confirmed == 1) {
            $this->confirmed_at = time();
            if (!$this->confirmed_by) {
                $this->confirmed_by = Yii::$app->user->id;
            }
        }

        return true;
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

    /**
     * Get all status labels
     * @return array
     */
    public static function getStatusLabels()
    {
        return [
            self::STATUS_NEED_TO_CALL => 'Need to call',
            self::STATUS_CALLED => 'Called',
        ];
    }

    /**
     * Get current status label
     * @return string
     */
    public function getStatusLabel()
    {
        $labels = self::getStatusLabels();
        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get status options for dropdowns
     * @return array
     */
    public static function getStatusOptions()
    {
        return self::getStatusLabels();
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
     * Gets query for [[HrEmployee]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getHrEmployee()
    {
        return $this->hasOne(User::class, ['id' => 'hr_employee_id']);
    }

    /**
     * Gets query for [[CallCenterEmployee]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCallCenterEmployee()
    {
        return $this->hasOne(User::class, ['id' => 'call_center_employee_id']);
    }

    /**
     * Gets query for [[ConfirmedBy]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getConfirmedBy()
    {
        return $this->hasOne(User::class, ['id' => 'confirmed_by']);
    }

    /**
     * Check if urgent call is confirmed
     * @return bool
     */
    public function isConfirmed()
    {
        return $this->is_confirmed == 1;
    }

    /**
     * Check if urgent call needs to be called
     * @return bool
     */
    public function needsCall()
    {
        return $this->status == self::STATUS_NEED_TO_CALL;
    }

    /**
     * Check if urgent call was already called
     * @return bool
     */
    public function wasCalled()
    {
        return $this->status == self::STATUS_CALLED;
    }

    /**
     * Mark as called
     * @param int|null $employeeId
     * @param string|null $comment
     * @return bool
     */
    public function markAsCalled($employeeId = null, $comment = null)
    {
        $this->status = self::STATUS_CALLED;

        if ($employeeId) {
            $this->call_center_employee_id = $employeeId;
        }

        if ($comment) {
            $this->call_center_comment = $comment;
        }

        return $this->save();
    }

    /**
     * Confirm urgent call
     * @param int|null $confirmedBy
     * @return bool
     */
    public function confirm($confirmedBy = null)
    {
        $this->is_confirmed = 1;
        $this->confirmed_at = time();
        $this->confirmed_by = $confirmedBy ?: Yii::$app->user->id;

        return $this->save();
    }
}