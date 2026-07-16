<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "logs_company_details".
 *
 * @property int $id
 * @property int $logs_company_id
 * @property string|null $data
 * @property string $data_type
 *
 * @property LogsCompany $log
 */
class LogsCompanyDetails extends ActiveRecord
{
    const TYPE_TEXT = 'text';
    const TYPE_JSON = 'json';

    public static function tableName()
    {
        return 'logs_company_details';
    }

    public function rules()
    {
        return [
            [['logs_company_id', 'data_type'], 'required'],
            [['logs_company_id'], 'integer'],
            [['data'], 'string'],
            [['data_type'], 'string', 'max' => 50],
            [['data_type'], 'in', 'range' => array_keys(self::getDataTypes())],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'logs_company_id' => 'Log company',
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
        return $this->hasOne(LogsCompany::class, ['id' => 'logs_company_id']);
    }
}
