<?php

namespace backend\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * UserTrainingProgress model
 *
 * @property int $id
 * @property int $user_id
 * @property int $current_module_id
 * @property int $current_module_attempts
 * @property int $last_attempt_at
 * @property float $last_attempt_score
 * @property string $completed_modules
 * @property int $started_at
 * @property int $updated_at
 */
class UserTrainingProgress extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_training_progress}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'started_at',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['user_id', 'current_module_id'], 'required'],
            [['user_id', 'current_module_id', 'current_module_attempts', 'last_attempt_at'], 'integer'],
            [['last_attempt_score'], 'number'],
            [['completed_modules'], 'string'],
            [['current_module_attempts'], 'default', 'value' => 0],
            [['last_attempt_score'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'current_module_id' => 'Current Module',
            'current_module_attempts' => 'Attempts',
            'last_attempt_at' => 'Last Attempt',
            'last_attempt_score' => 'Last Score',
            'completed_modules' => 'Completed',
            'started_at' => 'Started',
            'updated_at' => 'Updated',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCurrentModule()
    {
        return $this->hasOne(TrainingModule::class, ['id' => 'current_module_id']);
    }

    /**
     * Get array of completed module IDs
     * Converts "1,2,5" string to [1, 2, 5] array
     */
    public function getCompletedModulesArray()
    {
        if (empty($this->completed_modules)) {
            return [];
        }
        return array_map('intval', explode(',', $this->completed_modules));
    }

    /**
     * Add module to completed list
     */
    public function addCompletedModule($moduleId)
    {
        $completed = $this->getCompletedModulesArray();
        if (!in_array($moduleId, $completed)) {
            $completed[] = $moduleId;
            $this->completed_modules = implode(',', $completed);
        }
    }

    /**
     * Check if module is completed
     */
    public function isModuleCompleted($moduleId)
    {
        return in_array($moduleId, $this->getCompletedModulesArray());
    }
}