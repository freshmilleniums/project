<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\Task;
use common\services\TrainingProgressService;
use common\models\Companies;

/**
 * Checks overdue training tasks and marks them accordingly
 * Run every 15 minutes: *\/15 * * * * php yii training-task/check-overdue
 */
class TrainingTaskController extends Controller
{
    public function actionCheckOverdue()
    {
        echo "Starting overdue training tasks check...\n";

        $companies = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->all();

        if (empty($companies)) {
            echo "No running companies found.\n";
            return Controller::EXIT_CODE_NORMAL;
        }

        $trainingProgressService = new TrainingProgressService();
        $count = 0;

        foreach ($companies as $company) {
            try {
                Yii::$app->params['company_id'] = $company->id;

                $overdueTasks = Task::find()
                    ->where([
                        'is_training' => Task::IS_TRAINING,
                        'status'      => [Task::STATUS_NEW, Task::STATUS_IN_PROGRESS],
                    ])
                    ->andWhere(['IS NOT', 'due_date', null])
                    ->andWhere(['<', 'due_date', time()])
                    ->andWhere(['IS NOT', 'training_employee_id', null])
                    ->all();

                if (empty($overdueTasks)) {
                    //echo "No overdue training tasks found.\n";
                    continue;
                }

                foreach ($overdueTasks as $task) {
                    try {
                        $trainingProgressService->markDeadlineMissed($task->training_employee_id);
                        $count++;
                        echo "Marked deadline missed for employee #{$task->training_employee_id}, task #{$task->id}\n";
                    } catch (\Exception $e) {
                        Yii::error('TrainingTaskController: error processing task #' . $task->id . ': ' . $e->getMessage());
                    }
                }

            } finally {
                Yii::$app->params['company_id'] = 0;
            }
        }

        echo "Overdue training tasks check completed. Processed {$count} task(s).\n";

        return Controller::EXIT_CODE_NORMAL;
    }
}