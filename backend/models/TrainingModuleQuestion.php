<?php

namespace backend\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * TrainingModuleQuestion model
 *
 * @property int $id
 * @property int $module_id
 * @property string $question_text
 * @property int $type
 * @property int $sort
 * @property int $has_correct_answer
 * @property int $created_at
 */
class TrainingModuleQuestion extends \yii\db\ActiveRecord
{
    const TYPE_TEXT = 1;
    const TYPE_RADIO = 2;
    const TYPE_CHECKBOX = 3;

    public static function tableName()
    {
        return '{{%training_module_questions}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['module_id', 'question_text', 'type'], 'required'],
            [['module_id', 'type', 'sort', 'has_correct_answer'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_TEXT, self::TYPE_RADIO, self::TYPE_CHECKBOX]],
            [['has_correct_answer'], 'default', 'value' => 0],
            [['sort'], 'default', 'value' => 0],
            [['question_text'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'module_id' => 'Module',
            'question_text' => 'Question',
            'type' => 'Type',
            'sort' => 'Sort',
            'has_correct_answer' => 'Check Correctness',
            'created_at' => 'Created',
        ];
    }

    public function getModule()
    {
        return $this->hasOne(TrainingModule::class, ['id' => 'module_id']);
    }

    public function getOptions()
    {
        return $this->hasMany(TrainingQuestionOption::class, ['question_id' => 'id'])
            ->orderBy(['sort' => SORT_ASC]);
    }

    public static function getTypesList()
    {
        return [
            self::TYPE_TEXT => 'Text',
            self::TYPE_RADIO => 'Radio',
            self::TYPE_CHECKBOX => 'Checkbox',
        ];
    }

    public function getTypeName()
    {
        $types = self::getTypesList();
        return $types[$this->type] ?? 'Unknown';
    }
}