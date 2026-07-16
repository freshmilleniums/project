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
     * Get list of codes from REM1 to REM24
     * @return array
     */
    public static function getCodeList()
    {
        $codes = [];
        for ($i = 1; $i <= 24; $i++) {
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
            'REM1' => 'Sent 10 minutes after the first email. Condition - candidate did not log into personal account',
            'REM2' => 'Sent 3 hours after REM1. Condition - candidate did not log into personal account after REM1',
            'REM3' => 'Sent during test stage. If within 10 hours after status change to "ready to take test" there is no confirmation that test was passed',
            'REM4' => 'Sent 48 hours after REM3. Same conditions as REM3. If no changes after REM4 - candidate data is deleted from database',
            'REM5' => 'Sent during greeting stage. If welcome call goes to voicemail - CC employee sets appropriate status and candidate receives reminder',
            'REM6' => 'Sent after 24 hours. If status does not change within 24 hours after REM5 - send reminder. If status does not change 24 hours after REM6 - delete candidate from database',
            'REM7' => 'Sent during greeting stage. If welcome call failed because wrong number was provided - CC employee sets status and reminder is sent immediately',
            'REM8' => 'Sent after 5 hours. If 5 hours after REM7 CC status does not change - send reminder',
            'REM9' => 'Sent after 24 hours. Same marker and conditions as REM8. If status does not change after 48 hours - delete candidate from database',
            'REM10' => 'Sent during greeting stage. If CC set status unavailable/busy/no voice. Sent 1 hour after setting appropriate CC status',
            'REM11' => 'Sent after 24 hours. Same marker as REM10. If status does not change 24 hours after REM10 - send reminder. If status does not change 24 hours after REM11 - delete candidate from database',
            'REM12' => 'Sent after 24 hours. If 24 hours after setting appropriate status candidate took no action - send reminder',
            'REM13' => 'Sent 24 hours after REM12. If after REM12 within 24 hours CC did not change status - send reminder, delete from database after 48 hours',
            'REM14' => 'Sent when status is set. If CC sets appropriate status ("voicemail full") - send reminder',
            'REM15' => 'Sent 24 hours after REM14. If after REM14 within 24 hours candidate takes no action - send reminder, delete candidate data from database after 48 hours',
            'REM16' => 'Sent during signing stage, 10 minutes after notification that contract is available in personal account',
            'REM17' => 'Sent after 24 hours. If after REM16 within 24 hours candidate status does not change - send reminder',
            'REM18' => 'Sent after 24 hours. If after REM17 within 24 hours candidate status does not change - send reminder, delete candidate from database 48 hours after REM18',
            'REM19' => 'Sent during first package receipt stage. If 6 hours after first package delivery to courier (trackable by tracking number) he does not change package status/mark successful receipt - send reminder',
            'REM20' => 'Sent after 24 hours. If after REM19 within 24 hours courier did not mark package receipt - send reminder',
            'REM21' => 'Sent after receiving first package. If courier after receiving package within 6 hours did not follow instructions and change package status - send reminder',
            'REM22' => 'Sent after 24 hours. If after REM21 courier did not follow instructions and change package status - send reminder',
            'REM23' => 'Sent during package sending task execution stage. If within 6 hours after loading label and receiving sending instructions courier does not change task status - send reminder',
            'REM24' => 'Sent after 48 hours. If 48 hours after REM23 courier did not follow sending instructions and change task status - send reminder',
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