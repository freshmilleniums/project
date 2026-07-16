<?php

namespace backend\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\Json;

/**
 * UserModuleAnswer model
 *
 * @property int $id
 * @property int $user_id
 * @property int $module_id
 * @property int $question_id
 * @property string $question_text
 * @property string $answer_data
 * @property int $is_correct
 * @property int $created_at
 */
class UserModuleAnswer extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_module_answers}}';
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
            [['user_id', 'module_id', 'question_id', 'question_text'], 'required'],
            [['user_id', 'module_id', 'question_id', 'is_correct'], 'integer'],
            [['answer_data'], 'string'],
            [['is_correct'], 'default', 'value' => 0],
            [['question_text'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'module_id' => 'Module',
            'question_id' => 'Question',
            'question_text' => 'Question Text',
            'answer_data' => 'Answer',
            'is_correct' => 'Correct',
            'created_at' => 'Created',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Get answer data as array
     */
    public function getAnswerDataArray()
    {
        if (empty($this->answer_data)) {
            return [];
        }
        return Json::decode($this->answer_data);
    }

    /**
     * Set answer data from array
     */
    public function setAnswerDataArray($data)
    {
        $this->answer_data = Json::encode($data);
    }

    /**
     * Find all answers by user ID
     */
    public static function findAllByUserId($userId)
    {
        return self::find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }

    /**
     * Find answers by user and module
     */
    public static function findByUserAndModule($userId, $moduleId)
    {
        return self::find()
            ->where(['user_id' => $userId, 'module_id' => $moduleId])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }
}