<?php

namespace backend\models;

use Yii;

/**
 * TrainingQuestionOption model
 *
 * @property int $id
 * @property int $question_id
 * @property string $option_text
 * @property int $is_correct
 * @property int $sort
 */
class TrainingQuestionOption extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%training_question_options}}';
    }

    public function rules()
    {
        return [
            [['question_id', 'option_text'], 'required'],
            [['question_id', 'is_correct', 'sort'], 'integer'],
            [['is_correct'], 'default', 'value' => 0],
            [['sort'], 'default', 'value' => 0],
            [['option_text'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'question_id' => 'Question',
            'option_text' => 'Option',
            'is_correct' => 'Correct',
            'sort' => 'Sort',
        ];
    }

    public function getQuestion()
    {
        return $this->hasOne(TrainingModuleQuestion::class, ['id' => 'question_id']);
    }
}