<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\ScheduledCall;
use common\services\NotificationService;
use common\models\Companies;

/**
 * Console controller for scheduled call reminders
 */
class ScheduledCallController extends Controller
{
    /**
     * Send threshold notifications for upcoming scheduled calls (45/15/5 minutes before call)
     * Usage: php yii scheduled-call/notify
     */
    public function actionNotify()
    {
        echo "Starting scheduled call notifications processing...\n";

        $companies = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->all();

        if (empty($companies)) {
            echo "No running companies found.\n";
            return Controller::EXIT_CODE_NORMAL;
        }

        $notificationService = Yii::createObject(NotificationService::class);
        $sentCount = 0;

        foreach ($companies as $company) {
            try {
                Yii::$app->params['company_id'] = $company->id;

                $pending = ScheduledCall::getPendingNotifications();

                if (empty($pending)) {
                    echo "No pending scheduled call notifications.\n";
                    continue;
                }

                foreach ($pending as $item) {
                    /** @var ScheduledCall $call */
                    $call   = $item['call'];
                    $bucket = $item['bucket'];

                    if (!$call->operator || !$call->candidate) {
                        continue;
                    }

                    $time          = date('H:i', $call->scheduled_at);
                    $candidateName = trim($call->candidate->first_name . ' ' . $call->candidate->last_name);

                    $text = "You have a scheduled call with {$candidateName} at {$time} ({$bucket} min left).";

                    try {
                        $notificationService->createNotification($call->operator_id, $text, true);

                        $call->notified_at = $bucket;
                        $call->save(false, ['notified_at']);

                        $sentCount++;

                        echo "Notified operator #{$call->operator_id} ({$bucket} min) about call with {$candidateName} at {$time}\n";

                    } catch (\Exception $e) {
                        Yii::error('Failed to send scheduled call notification: ' . $e->getMessage());
                        Yii::stderr("Error notifying operator #{$call->operator_id}: " . $e->getMessage() . "\n");
                    }

                }

            } finally {
                Yii::$app->params['company_id'] = 0;
            }
        }

        echo "Scheduled call notifications processing completed. Sent {$sentCount} notification(s).\n";

        return Controller::EXIT_CODE_NORMAL;
    }
}