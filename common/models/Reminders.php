<?php
namespace common\models;
use Yii;
/**
 * This is the model class for table "reminders".
 *
 * @property int $id
 * @property string $code
 * @property string|null $text
 */
class Reminders extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reminders';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code'], 'required'],
            [['text'], 'string'],
            [['code'], 'string', 'max' => 255],
            [['code'], 'unique'],
            [['code'], 'in', 'range' => array_keys(self::getCodeList())],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'Code',
            'text' => 'Text',
        ];
    }

    /**
     * Get list of codes from REM1 to REM10
     * @return array
     */
    public static function getCodeList()
    {
        $codes = [];
        for ($i = 1; $i <= 10; $i++) {
            $codes['REM' . $i] = 'REM' . $i;
        }
        return $codes;
    }

    /**
     * Get descriptions for codes
     * @return array
     */
    public static function getCodeDescriptions()
    {
        return [
            'REM1'  => 'Sent 10 min after welcome notification. Condition - employee did not log into personal account',
            'REM2'  => 'Sent 24 hours after REM1. Condition - employee still not logged in. Employee is archived',
            'REM3'  => 'Sent 10 min after contract was sent. Condition - contract not signed',
            'REM4'  => 'Sent 24 hours after REM3. Condition - contract still not signed',
            'REM5'  => 'Sent 48 hours after REM4. Condition - contract still not signed. Employee is moved to archived',
            'REM6'  => 'Sent 10 hours after last training activity. Condition - training module not completed',
            'REM7'  => 'Sent 48 hours after REM6. Condition - training still not progressed. Employee is archived',
            'REM8'  => 'Sent 24 hours after final assignment was issued. Condition - final task not completed',
            'REM9'  => 'Sent 24 hours after REM8. Condition - final task still not completed',
            'REM10' => 'Sent 48 hours after REM9. Condition - final task still not completed',
        ];
    }

    /**
     * Get description for code
     * @return string
     */
    public function getCodeDescription()
    {
        $descriptions = self::getCodeDescriptions();
        return isset($descriptions[$this->code]) ? $descriptions[$this->code] : '';
    }

    /**
     * Get all reminders as array [code => text]
     * @return array
     */
    public static function getAllReminders()
    {
        $reminders = [];
        $models = self::find()->all();
        foreach ($models as $model) {
            $reminders[$model->code] = $model->text;
        }
        return $reminders;
    }

    /**
     * Get reminder text by code
     * @param string $code
     * @return string|null
     */
    public static function getReminderText($code)
    {
        $model = self::findOne(['code' => $code]);
        return $model ? $model->text : null;
    }
}