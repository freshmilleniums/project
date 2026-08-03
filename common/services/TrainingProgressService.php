<?php

namespace common\services;

use Yii;
use backend\models\User;
use backend\models\UserTrainingProgress;
use common\models\UserComments;

class TrainingProgressService
{
    public function completeModule(int $moduleId, int $employeeId): bool
    {
        $employee = User::findOne($employeeId);
        if (!$employee) {
            Yii::error('TrainingProgressService: employee not found: ' . $employeeId);
            return false;
        }

        $progress = UserTrainingProgress::findOne(['user_id' => $employeeId]);
        if (!$progress) {
            Yii::error('TrainingProgressService: progress not found for employee: ' . $employeeId);
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();

        $progress->addCompletedModule($moduleId);
        if (!$progress->save(false)) {
            $transaction->rollBack();
            return false;
        }

        $employee->substatus = User::SUBSTATUS_ACTIVE_EMPLOYEE;
        if (!$employee->save(false)) {
            $transaction->rollBack();
            Yii::error('TrainingProgressService: failed to update substatus for employee: ' . $employeeId);
            return false;
        }

        $comment = new UserComments();
        $comment->user_id      = $employeeId;
        $comment->comment      = 'Final task approved. Employee activated.';
        $comment->commented_by = 0;
        $comment->save();

        $transaction->commit();
        return true;
    }

    public function markDeadlineMissed(int $employeeId): void
    {
        $employee = User::findOne($employeeId);
        if (!$employee) {
            return;
        }

        $employee->substatus = User::SUBSTATUS_UNCOMPLETED_TASK;
        $employee->save(false);

        $comment = new UserComments();
        $comment->user_id      = $employeeId;
        $comment->comment      = 'Final task deadline missed. Requires review.';
        $comment->commented_by = 0;
        $comment->save();
    }
}