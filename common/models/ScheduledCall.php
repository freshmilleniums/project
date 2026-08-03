<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id
 * @property int $company_id
 * @property int $operator_id
 * @property int $candidate_id
 * @property int $scheduled_at
 * @property string|null $comment
 * @property int $is_done
 * @property int|null $notified_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property \backend\models\User $operator
 * @property \backend\models\User $candidate
 */
class ScheduledCall extends \yii\db\ActiveRecord
{
    /**
     * Virtual attribute for date/time picker input
     * @var string|null
     */
    public $scheduled_at_formatted;

    public static function tableName()
    {
        return '{{%scheduled_calls}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['scheduled_at_formatted'], 'required'],
            [['scheduled_at_formatted'], 'safe'],
            [['operator_id', 'candidate_id', 'company_id', 'scheduled_at'], 'integer'],
            [['operator_id', 'candidate_id', 'scheduled_at'], 'default', 'value' => null],
            [['is_done'], 'boolean'],
            [['is_done'], 'default', 'value' => 0],
            [['notified_at'], 'integer'],
            [['notified_at'], 'default', 'value' => null],
            [['comment'], 'string', 'max' => 500],
            [['comment'], 'default', 'value' => null],
            [['operator_id'], 'exist', 'targetClass' => \backend\models\User::class,
                'targetAttribute' => 'id'],
            [['candidate_id'], 'exist', 'targetClass' => \backend\models\User::class,
                'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'                     => 'ID',
            'company_id'             => 'Company',
            'operator_id'            => 'Operator',
            'candidate_id'           => 'Candidate',
            'scheduled_at'           => 'Scheduled At',
            'scheduled_at_formatted' => 'Date & Time',
            'comment'                => 'Comment',
            'is_done'                => 'Done',
            'notified_at'            => 'Notified At',
            'created_at'             => 'Created At',
            'updated_at'             => 'Updated At',
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->company_id  = Yii::$app->params['company_id'] ?? 0;
            $this->operator_id = Yii::$app->user->id;
        }

        if (!empty($this->scheduled_at_formatted)) {
            $this->scheduled_at = strtotime($this->scheduled_at_formatted);
        }

        return true;
    }

    public function getOperator()
    {
        return $this->hasOne(\backend\models\User::class, ['id' => 'operator_id']);
    }

    public function getCandidate()
    {
        return $this->hasOne(\backend\models\User::class, ['id' => 'candidate_id']);
    }

    /**
     * Get upcoming calls for operator within next 45 minutes
     */
    public static function getUpcoming(int $operatorId): array
    {
        return static::find()
            ->where(['operator_id' => $operatorId, 'is_done' => 0])
            ->andWhere(['<=', 'scheduled_at', time() + (45 * 60)])
            ->andWhere(['>', 'scheduled_at', time()])
            ->orderBy(['scheduled_at' => SORT_ASC])
            ->with('candidate')
            ->all();
    }

    /**
     * Check if operator has upcoming calls within 45 minutes
     */
    public static function hasUpcoming(int $operatorId): bool
    {
        return static::find()
            ->where(['operator_id' => $operatorId, 'is_done' => 0])
            ->andWhere(['<=', 'scheduled_at', time() + (45 * 60)])
            ->andWhere(['>', 'scheduled_at', time()])
            ->exists();
    }

    /**
     * Get calls that need a threshold notification (45/15/5 min before call)
     * Returns array of ['call' => ScheduledCall, 'bucket' => int]
     */
    public static function getPendingNotifications(): array
    {
        $now = time();

        $calls = static::find()
            ->where(['is_done' => 0])
            ->andWhere(['>', 'scheduled_at', $now])
            ->andWhere(['<=', 'scheduled_at', $now + (45 * 60)])
            ->with('candidate', 'operator')
            ->all();

        $result = [];

        foreach ($calls as $call) {
            $minutesLeft = ($call->scheduled_at - $now) / 60;

            if ($minutesLeft > 15) {
                $bucket = 45;
            } elseif ($minutesLeft > 5) {
                $bucket = 15;
            } else {
                $bucket = 5;
            }

            // Send if not notified yet, or last notification was for a farther threshold
            if ($call->notified_at === null || (int)$call->notified_at > $bucket) {
                $result[] = ['call' => $call, 'bucket' => $bucket];
            }
        }

        return $result;
    }
}