<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * TestItemOption model
 *
 * @property int $id
 * @property int $test_item_id
 * @property string $option_text
 * @property int $sort
 *
 * @property TestItem $testItem
 */
class TestItemOption extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%test_item_options}}';
    }

    public function rules()
    {
        return [
            [['test_item_id', 'option_text'], 'required'],
            [['test_item_id', 'sort'], 'integer'],
            [['option_text'], 'string', 'max' => 255],
            [['sort'], 'default', 'value' => 0]
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'test_item_id' => 'Test Item ID',
            'option_text' => 'Option Text',
            'sort' => 'Sort Order',
        ];
    }

    public function getTestItem()
    {
        return $this->hasOne(TestItem::class, ['id' => 'test_item_id']);
    }
}