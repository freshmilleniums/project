<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "logs_admin_details".
 *
 * @property int $id
 * @property int $logs_admin_id
 * @property string|null $data
 * @property string $data_type
 *
 * @property LogsAdmin $log
 */
class LogsAdminDetails extends ActiveRecord
{
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';

    public static function tableName()
    {
        return 'logs_admin_details';
    }

    public function rules()
    {
        return [
            [['logs_admin_id', 'data_type'], 'required'],
            [['logs_admin_id'], 'integer'],
            [['data'], 'string'],
            [['data_type'], 'string', 'max' => 50],
            [['data_type'], 'in', 'range' => array_keys(self::getDataTypes())],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'logs_admin_id' => 'Log admin ID',
            'data' => 'Data',
            'data_type' => 'Data Type',
        ];
    }

    public static function getDataTypes(): array
    {
        return [
            self::TYPE_TEXT => 'Text',
            self::TYPE_JSON => 'JSON',
        ];
    }

    public function getLog()
    {
        return $this->hasOne(LogsAdmin::class, ['id' => 'logs_admin_id']);
    }
}
