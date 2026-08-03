<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\services\RemindersService;
use common\models\Companies;

/**
 * Console controller for processing reminders
 */
class RemindersController extends Controller
{
    /**
     * Process all reminders
     * Usage: php yii reminders/process-all
     */
    public function actionProcessAll()
    {
        echo "Starting reminders processing...\n";

        $companies = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->all();

        if (empty($companies)) {
            echo "No running companies found.\n";
            return Controller::EXIT_CODE_NORMAL;
        }

        foreach ($companies as $company) {
            echo "-- Company #{$company->id} ({$company->name})\n";
            try {
                Yii::$app->params['company_id'] = $company->id;

                $remindersService = Yii::createObject(RemindersService::class);

                for ($i = 1; $i <= 10; $i++) {
                    $reminderCode = 'REM' . $i;
                    $methodName = 'process' . $reminderCode;

                    if (method_exists($remindersService, $methodName)) {
                        $remindersService->$methodName();
                    } else {
                        Yii::stderr("Method {$methodName} not found.\n");
                    }
                }
            } finally {
                Yii::$app->params['company_id'] = 0;
            }
        }

        Yii::$app->params['company_id'] = 0;
        echo "Reminders processing completed.\n";
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Process specific reminder by code
     * Usage: php yii reminders/process-reminder REM1
     *
     * @param string $code Reminder code (REM1-REM10)
     */
    public function actionProcessReminder($code)
    {
        echo "Starting processing for reminder: {$code}\n";

        if (!preg_match('/^REM([1-9]|10)$/', $code)) {
            Yii::stderr("Invalid reminder code format: {$code}. Use REM1 to REM10.\n");
            return Controller::EXIT_CODE_ERROR;
        }

        $companies = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->all();

        if (empty($companies)) {
            echo "No running companies found.\n";
            return Controller::EXIT_CODE_NORMAL;
        }

        $methodName = 'process' . $code;

        foreach ($companies as $company) {
            echo "-- Company #{$company->id} ({$company->name})\n";
            try {
                Yii::$app->params['company_id'] = $company->id;

                $remindersService = Yii::createObject(RemindersService::class);

                if (method_exists($remindersService, $methodName)) {
                    $remindersService->$methodName();
                } else {
                    Yii::stderr("Method {$methodName} not found in RemindersService.\n");
                    return Controller::EXIT_CODE_ERROR;
                }
            } finally {
                Yii::$app->params['company_id'] = 0;
            }
        }

        echo "Processing for {$code} completed.\n";
        return Controller::EXIT_CODE_NORMAL;
    }
}