<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * TestItem model
 *
 * @property int $id
 * @property string $question_name
 * @property int $type
 * @property int $sort
 *
 * @property TestItemOption[] $options
 */
class TestItem extends ActiveRecord
{
    const TYPE_TEXT = 1;
    const TYPE_RADIO = 2;
    const TYPE_CHECKBOX = 3;
    public $optionsData = [];

    public static function tableName()
    {
        return '{{%test_items}}';
    }

    public function rules()
    {
        return [
            [['question_name', 'type'], 'required'],
            [['type', 'sort'], 'integer'],
            [['question_name'], 'string', 'max' => 255],
            [['sort'], 'default', 'value' => 0],
            [['optionsData'], 'validateOptions']
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'question_name' => 'Question Name',
            'type' => 'Question Type',
            'sort' => 'Sort Order',
        ];
    }

    public function getOptions()
    {
        return $this->hasMany(TestItemOption::class, ['test_item_id' => 'id'])
            ->orderBy('sort ASC');
    }

    public static function getTypesList()
    {
        return [
            self::TYPE_TEXT => 'Text',
            self::TYPE_RADIO => 'Radio',
            self::TYPE_CHECKBOX => 'Checkbox'
        ];
    }

    public function getTypeName()
    {
        $types = self::getTypesList();
        return $types[$this->type] ?? 'Unknown';
    }

    /**
     * Custom validation for options
     */
    public function validateOptions($attribute, $params)
    {
        // Only validate options for radio and checkbox types
        if (in_array($this->type, [self::TYPE_RADIO, self::TYPE_CHECKBOX])) {
            $hasValidOption = false;

            // Check if optionsData is set (from controller)
            if (!empty($this->optionsData)) {
                foreach ($this->optionsData as $option) {
                    if (!empty(trim($option))) {
                        $hasValidOption = true;
                        break;
                    }
                }
            }

            // If no options data provided, check existing options for update scenario
            if (!$hasValidOption && !$this->isNewRecord) {
                $existingOptions = $this->getOptions()->all();
                if (!empty($existingOptions)) {
                    $hasValidOption = true;
                }
            }

            if (!$hasValidOption) {
                $this->addError('type', 'For Radio and Checkbox types, you must provide at least one answer option.');
            }
        }
    }

    /**
     * Set options data for validation
     */
    public function setOptionsForValidation($options)
    {
        $this->optionsData = $options;
    }
}