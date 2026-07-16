<?php

namespace backend\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * TrainingModule model
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $sort
 * @property int $is_active
 * @property int $passing_score
 * @property int $created_at
 * @property int $updated_at
 */
class TrainingModule extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%training_modules}}';
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
            [['title'], 'required'],
            [['content'], 'string'],
            [['sort', 'is_active', 'passing_score'], 'integer'],
            [['passing_score'], 'default', 'value' => 80],
            [['is_active'], 'default', 'value' => 1],
            [['sort'], 'default', 'value' => 0],
            [['title'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'content' => 'Content',
            'sort' => 'Sort',
            'is_active' => 'Active',
            'passing_score' => 'Passing Score (%)',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    public function getQuestions()
    {
        return $this->hasMany(TrainingModuleQuestion::class, ['module_id' => 'id'])
            ->orderBy(['sort' => SORT_ASC]);
    }

    public function getQuestionCount()
    {
        return TrainingModuleQuestion::find()
            ->where(['module_id' => $this->id])
            ->count();
    }
}