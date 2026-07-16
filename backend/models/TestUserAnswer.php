<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use backend\models\TestItem;

/**
 * TestUserAnswer model
 *
 * @property int $id
 * @property int $user_id
 * @property int $test_item_id
 * @property string $question_name
 * @property string $answer
 *
 * @property User $user
 * @property TestItem $testItem
 */
class TestUserAnswer extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%test_user_answers}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'test_item_id', 'question_name'], 'required'],
            [['user_id', 'test_item_id'], 'integer'],
            [['question_name'], 'string', 'max' => 255],
            [['answer'], 'string', 'max' => 3000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'test_item_id' => 'Test Item ID',
            'question_name' => 'Question Name',
            'answer' => 'Answer',
        ];
    }

    /**
     * Get user relation
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(\common\models\User::class, ['id' => 'user_id']);
    }

    /**
     * Get test item relation
     * @return \yii\db\ActiveQuery
     */
    public function getTestItem()
    {
        return $this->hasOne(TestItem::class, ['id' => 'test_item_id']);
    }

    /**
     * Get decoded answer data
     * @return array|null
     */
    public function getAnswerData()
    {
        if (empty($this->answer)) {
            return null;
        }

        try {
            return Json::decode($this->answer);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get human readable answer text
     * @return string
     */
    public function getAnswerText()
    {
        $answerData = $this->getAnswerData();

        if (!$answerData) {
            return '';
        }

        // Handle different answer types
        if (isset($answerData['text'])) {
            // Text answer
            return $answerData['text'];
        } elseif (isset($answerData['selected'])) {
            // Radio or checkbox answer
            $selected = $answerData['selected'];

            if (is_array($selected) && !empty($selected)) {
                if (isset($selected[0]['text'])) {
                    // Multiple selections (checkbox)
                    $texts = [];
                    foreach ($selected as $option) {
                        if (isset($option['text'])) {
                            $texts[] = $option['text'];
                        }
                    }
                    return implode(', ', $texts);
                } elseif (isset($selected['text'])) {
                    // Single selection (radio)
                    return $selected['text'];
                }
            }
        }

        return '';
    }

    /**
     * Find all user answers
     * @param int $userId
     * @return static[]
     */
    public static function findAllByUserId($userId)
    {
        return static::find()
            ->where(['user_id' => $userId])
            ->indexBy('test_item_id')
            ->all();
    }

    /**
     * Delete all user answers
     * @param int $userId
     * @return int number of deleted records
     */
    public static function deleteAllByUserId($userId)
    {
        return static::deleteAll(['user_id' => $userId]);
    }

    /**
     * Create answer from test item and user input
     * @param int $userId
     * @param TestItem $testItem
     * @param mixed $answer
     * @return static
     */
    public static function createFromTestItem($userId, $testItem, $answer)
    {
        $model = new static();
        $model->user_id = $userId;
        $model->test_item_id = $testItem->id;
        $model->question_name = $testItem->question_name;
        $model->answer = static::prepareAnswerJson($testItem, $answer);

        return $model;
    }

    /**
     * Prepare answer data for JSON storage
     * @param TestItem $testItem
     * @param mixed $answer
     * @return string
     */
    public static function prepareAnswerJson($testItem, $answer)
    {
        switch ($testItem->type) {
            case TestItem::TYPE_TEXT:
                return Json::encode(['text' => (string)$answer]);

            case TestItem::TYPE_RADIO:
                // For radio, answer is a single option ID
                $selectedOption = null;
                if (!empty($answer)) {
                    foreach ($testItem->options as $option) {
                        if ($option->id == $answer) {
                            $selectedOption = [
                                'id' => $option->id,
                                'text' => $option->option_text
                            ];
                            break;
                        }
                    }
                }
                return Json::encode(['selected' => $selectedOption]);

            case TestItem::TYPE_CHECKBOX:
                // For checkbox, answer is an array of option IDs
                $selectedOptions = [];
                if (is_array($answer)) {
                    foreach ($testItem->options as $option) {
                        if (in_array($option->id, $answer)) {
                            $selectedOptions[] = [
                                'id' => $option->id,
                                'text' => $option->option_text
                            ];
                        }
                    }
                }
                return Json::encode(['selected' => $selectedOptions]);
        }

        return Json::encode(['raw' => $answer]);
    }
}