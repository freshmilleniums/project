<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "project_employee".
 *
 * @property int $id
 * @property int $project_id
 * @property int $employee_id
 * @property int|null $assigned_by
 * @property int|null $assigned_at
 *
 * @property Project $project
 * @property User $employee
 * @property User $assignedBy
 */
class ProjectEmployee extends ActiveRecord
{
    public static function tableName()
    {
        return 'project_employee';
    }

    public function rules()
    {
        return [
            [['project_id', 'employee_id'], 'required'],
            [['project_id', 'employee_id', 'assigned_by', 'assigned_at'], 'integer'],
            [['project_id'], 'exist', 'skipOnError' => true, 'targetClass' => Project::class, 'targetAttribute' => ['project_id' => 'id']],
            [['employee_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['employee_id' => 'id']],
            [['assigned_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['assigned_by' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'project_id'  => 'Project ID',
            'employee_id' => 'Employee ID',
            'assigned_by' => 'Assigned By',
            'assigned_at' => 'Assigned At',
        ];
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getEmployee()
    {
        return $this->hasOne(User::class, ['id' => 'employee_id']);
    }

    public function getAssignedBy()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_by']);
    }
}