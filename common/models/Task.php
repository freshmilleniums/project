<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "tasks".
 *
 * @property int $id
 * @property string $title
 * @property string|null $subject
 * @property string|null $description
 * @property int|null $template_id
 * @property int|null $assigned_to
 * @property int|null $priority
 * @property int|null $status
 * @property int|null $due_date
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $assignedUser
 * @property User $creator
 * @property TasksDocuments[] $documents
 */
class Task extends \yii\db\ActiveRecord
{
    const PRIORITY_LOW = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_HIGH = 3;
    const PRIORITY_URGENT = 4;

    const STATUS_NEW = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_ON_HOLD = 4;
    const STATUS_CANCELLED = 5;
    const IS_TRAINING = 1;
    const IS_NOT_TRAINING = 0;

    public $due_date_formatted;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks';
    }

    /**
     * {@inheritdoc}
     */
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
            [['title'], 'required'],
            [['template_id', 'assigned_to', 'priority', 'status', 'due_date', 'created_by', 'created_at', 'updated_at',
                'is_training', 'training_module_id', 'training_employee_id'], 'integer'],
            [['title', 'subject'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 5000],
            [['priority'], 'in', 'range' => array_keys(self::getPriorityList())],
            [['status'], 'in', 'range' => array_keys(self::getStatusList())],
            [['due_date_formatted'], 'safe'],
            [['is_training'], 'default', 'value' => self::IS_NOT_TRAINING],
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
            'title' => 'Title',
            'subject' => 'Subject',
            'description' => 'Description',
            'template_id' => 'Template',
            'assigned_to' => 'Assigned To',
            'priority' => 'Priority',
            'status' => 'Status',
            'due_date' => 'Due Date',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'due_date_formatted' => 'Due Date',
            'is_training'          => 'Training Task',
            'training_module_id'   => 'Training Module',
            'training_employee_id' => 'Training Employee',
        ];
    }

    /**
     * @return array
     */
    public static function getPriorityList()
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /**
     * @return string
     */
    public function getPriorityName()
    {
        $priorityList = self::getPriorityList();
        return isset($priorityList[$this->priority]) ? $priorityList[$this->priority] : 'Unknown';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @return string
     */
    public function getStatusName()
    {
        $statusList = self::getStatusList();
        return isset($statusList[$this->status]) ? $statusList[$this->status] : 'Unknown';
    }

    /**
     * @return string|null
     */
    public function getAssignedUserName()
    {
        if ($this->assigned_to && $this->assignedUser) {
            return trim($this->assignedUser->first_name . ' ' . $this->assignedUser->last_name);
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getCreatorName()
    {
        if ($this->created_by && $this->creator) {
            return trim($this->creator->first_name . ' ' . $this->creator->last_name);
        }
        return null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAssignedUser()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_to']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDocuments()
    {
        return $this->hasMany(TasksDocuments::class, ['task_id' => 'id']);
    }

    /**
     */
    public function afterFind()
    {
        parent::afterFind();

        if ($this->due_date) {
            $this->due_date_formatted = date('Y-m-d H:i', $this->due_date);
        }
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
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                if ($this->company_id === null) {
                    $this->company_id = \Yii::$app->params['company_id'] ?? null;
                }
                if ($this->company_id === null || $this->company_id < 1) {
                    return false;
                }
            }
            // Convert formatted date string to UNIX timestamp
            if (!empty($this->due_date_formatted)) {
                $timestampValue = strtotime($this->due_date_formatted);
                if ($timestampValue !== false) {
                    $this->due_date = $timestampValue;
                } else {
                    $this->due_date = null;
                }
            } elseif ($this->due_date_formatted === '') {
                // Empty string means clear the date
                $this->due_date = null;
            }
            return true;
        }
        return false;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if (
            !$insert &&
            isset($changedAttributes['status']) &&
            (int)$this->status === self::STATUS_COMPLETED &&
            (int)$this->is_training === self::IS_TRAINING &&
            $this->training_module_id !== null &&
            $this->training_employee_id !== null
        ) {
            $trainingProgressService = new \common\services\TrainingProgressService();
            $trainingProgressService->completeModule($this->training_module_id, $this->training_employee_id);
        }
    }
}