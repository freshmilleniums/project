<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\VarDumper;

/**
 * This is the model class for table "logs_admin".
 */
class LogsAdmin extends \yii\db\ActiveRecord
{
    const ACTION_LOGIN = 'login';
    // Add company-specific actions
    const ACTION_COMPANY_CREATE = 'company_create';
    const ACTION_COMPANY_UPDATE = 'company_update';
    const ACTION_COMPANY_DELETE = 'company_delete';


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'logs_admin';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'date'], 'integer'],
            [['action_type', 'section'], 'string', 'max' => 50],
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'date',
                'updatedAtAttribute' => false,
                'value' => function () { return time(); },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getActionTypeList()
    {
        return [
            self::ACTION_LOGIN => 'Login',
            self::ACTION_COMPANY_CREATE => 'Company Create',
            self::ACTION_COMPANY_UPDATE => 'Company Update',
            self::ACTION_COMPANY_DELETE => 'Company Delete',
        ];
    }

    /**
     * Relation to details
     */
    public function getLogsAdminDetails()
    {
        return $this->hasMany(LogsAdminDetails::class, ['logs_admin_id' => 'id']);
    }

    /**
     * Relation to user
     */
    public function getUser()
    {
        return $this->hasOne(\backend\models\User::class, ['id' => 'user_id']);
    }

    public function addDetail($data, string $dataType = LogsAdminDetails::TYPE_TEXT): bool
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            if (!$this->save()) {
                $transaction->rollBack();
                return false;
            }

            $detail = new LogsAdminDetails();
            $detail->logs_admin_id = $this->id;
            $detail->data_type = $dataType;

            if ($dataType === LogsAdminDetails::TYPE_JSON) {
                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    $detail->data = ($decoded === null && json_last_error() !== JSON_ERROR_NONE)
                        ? $data
                        : json_encode($decoded, JSON_UNESCAPED_UNICODE);
                } else {
                    $detail->data = json_encode($data, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $detail->data = is_scalar($data) ? (string)$data : VarDumper::export($data);
            }

            if (!$detail->save()) {
                $transaction->rollBack();
                return false;
            }

            $transaction->commit();
            return true;

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Failed to save admin log detail: ' . $e->getMessage());
            return false;
        }
    }
}
