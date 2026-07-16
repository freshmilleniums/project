<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use backend\models\User;
use yii\helpers\VarDumper;

/**
 * This is the model class for table "logs_company".
 * Autonomous logging - logs are saved independently of main process failures
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $action_type
 * @property int $date
 *
 * @property LogsCompanyDetails[] $details
 * @property Companies $company
 * @property User $user
 */
class LogsCompany extends ActiveRecord
{
    const ACTION_DEPLOY = 'deploy';
    const ACTION_STOP = 'stop';
    const ACTION_RESTART = 'restart';
    const ACTION_REDEPLOY = 'redeploy';
    const ACTION_START = 'start';

    public static function tableName()
    {
        return 'logs_company';
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

    public function rules()
    {
        return [
            [['company_id', 'user_id', 'action_type'], 'required'],
            [['company_id', 'user_id', 'date'], 'integer'],
            [['action_type'], 'string', 'max' => 50],
            [['action_type'], 'in', 'range' => array_keys(self::getActionTypes())],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'company_id' => 'Company ID',
            'user_id' => 'User ID',
            'action_type' => 'Action Type',
            'date' => 'Date',
        ];
    }

    public static function getActionTypes(): array
    {
        return [
            self::ACTION_DEPLOY => 'Deploy',
            self::ACTION_STOP => 'Stop',
            self::ACTION_RESTART => 'Restart',
            self::ACTION_REDEPLOY => 'Re-Deploy',
        ];
    }

    public function getDetails()
    {
        return $this->hasMany(LogsCompanyDetails::class, ['logs_company_id' => 'id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Companies::class, ['id' => 'company_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Autonomous log detail adding - NEVER uses transactions
     * Logs are saved INDEPENDENTLY of main process state
     *
     * @param mixed $data
     * @param string $type
     * @return LogsCompanyDetails|false
     */
    public function addDetail($data, string $type = LogsCompanyDetails::TYPE_TEXT)
    {
        try {

            if ($this->isNewRecord) {
                if (!$this->save(false)) {
                    Yii::error('Failed to save main log record: ' . json_encode($this->getErrors()), 'deployment');
                    return false;
                }
                Yii::info("Main log saved: ID {$this->id}", 'deployment');
            }

            // Create log detail
            $detail = new LogsCompanyDetails();
            $detail->logs_company_id = $this->id;
            $detail->data_type = $type;

            if ($type === LogsCompanyDetails::TYPE_JSON) {
                if (is_string($data)) {
                    // Check if string is valid JSON
                    $decoded = json_decode($data, true);
                    $detail->data = ($decoded === null && json_last_error() !== JSON_ERROR_NONE)
                        ? json_encode(['raw_data' => $data])
                        : json_encode($decoded, JSON_UNESCAPED_UNICODE);
                } else {
                    // Encode array/object to JSON
                    $detail->data = json_encode($data, JSON_UNESCAPED_UNICODE);
                }
            } else {
                // For text type
                $detail->data = is_scalar($data) ? (string)$data : VarDumper::export($data);
            }

            if (!$detail->save(false)) {
                Yii::error('Failed to save log detail: ' . json_encode($detail->getErrors()), 'deployment');
                return false;
            }

            // Log successful save for debugging
            if ($type === LogsCompanyDetails::TYPE_JSON && is_array($data) && isset($data['step'])) {
                Yii::info("Log detail saved: {$data['step']} (Detail ID: {$detail->id})", 'deployment');
            }

            return $detail;

        } catch (\Exception $e) {
            Yii::error('Exception in addDetail(): ' . $e->getMessage() . "\nData: " . VarDumper::export($data), 'deployment');

            // Try to save at least error information
            try {
                $errorDetail = new LogsCompanyDetails();
                $errorDetail->logs_company_id = $this->id;
                $errorDetail->data_type = LogsCompanyDetails::TYPE_JSON;
                $errorDetail->data = json_encode([
                    'step' => 'Logging error occurred',
                    'error' => true,
                    'message' => 'Failed to save log: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                $errorDetail->save(false);
            } catch (\Exception $e2) {
                // Even this failed, log to file
                error_log("Critical logging failure: " . $e->getMessage() . " | " . $e2->getMessage());
            }

            return false;
        }
    }

    /**
     *Log critical errors that should always be saved
     */
    public function addCriticalError($errorMessage, $stackTrace = null, $additionalData = [])
    {
        try {
            if ($this->isNewRecord) {
                $this->save(false);
            }

            $errorData = [
                'step' => 'Critical Error',
                'error' => true,
                'critical' => true,
                'message' => $errorMessage,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'failed'
            ];

            if ($stackTrace) {
                $errorData['stack_trace'] = $stackTrace;
            }

            if (!empty($additionalData)) {
                $errorData = array_merge($errorData, $additionalData);
            }

            return $this->addDetail($errorData, LogsCompanyDetails::TYPE_JSON);

        } catch (\Exception $e) {
            // Last resort - direct SQL
            try {
                $sql = "INSERT INTO logs_company_details (logs_company_id, data_type, data) VALUES (:log_id, 'json', :data)";
                Yii::$app->db->createCommand($sql, [
                    ':log_id' => $this->id,
                    ':data' => json_encode([
                        'step' => 'Emergency Error Log',
                        'error' => true,
                        'message' => $errorMessage,
                        'logging_error' => $e->getMessage()
                    ])
                ])->execute();
            } catch (\Exception $e3) {
                error_log("EMERGENCY: Cannot save critical error to DB: {$errorMessage}");
            }
        }
    }

    /**
     * Create deployment log with error protection
     */
    public static function createDeploymentLog($companyId, $userId, $actionType = self::ACTION_DEPLOY)
    {
        try {
            $log = new self();
            $log->company_id = $companyId;
            $log->user_id = $userId;
            $log->action_type = $actionType;

            if (!$log->save(false)) {
                Yii::error('Failed to create deployment log: ' . json_encode($log->getErrors()), 'deployment');
                return false;
            }

            // Add initial entry
            $log->addDetail([
                'step' => 'Deployment initialized',
                'progress' => 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'action_type' => $actionType
            ], LogsCompanyDetails::TYPE_JSON);

            return $log;

        } catch (\Exception $e) {
            Yii::error('Exception creating deployment log: ' . $e->getMessage(), 'deployment');
            return false;
        }
    }

    /**
     * Safe progress update
     */
    public function updateProgress($step, $progress, $additionalData = [])
    {
        $data = array_merge([
            'step' => $step,
            'progress' => (int)$progress,
            'timestamp' => date('Y-m-d H:i:s')
        ], $additionalData);

        return $this->addDetail($data, LogsCompanyDetails::TYPE_JSON);
    }
}