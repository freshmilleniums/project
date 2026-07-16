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
            [['template_id', 'assigned_to', 'priority', 'status', 'due_date', 'created_by', 'created_at', 'updated_at'], 'integer'],
            [['title', 'subject'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 5000],
            [['priority'], 'in', 'range' => array_keys(self::getPriorityList())],
            [['status'], 'in', 'range' => array_keys(self::getStatusList())],
            [['due_date_formatted'], 'safe'],
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

    /**
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
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
}