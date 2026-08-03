<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use backend\models\User;

/**
 * Tracks user activity time and resets daily counters
 */
class UserActivityController extends Controller
{
    /**
     * Add 5 minutes to total_time_today and total_time_all for all online users
     * Run every 5 minutes: *\/5 * * * * php yii user-activity/track
     */
    public function actionTrack()
    {
        $threshold = time() - 5 * 60;

        $count = User::updateAllCounters(
            [
                'total_time_today' => 300,
                'total_time_all'   => 300,
            ],
            ['and', ['>', 'last_activity', $threshold]]
        );

        Yii::info("Activity tracked for {$count} active users", __METHOD__);
    }

    /**
     * Reset total_time_today to 0 for all users
     * Run daily at midnight: 0 0 * * * php yii user-activity/reset-daily
     */
    public function actionResetDaily()
    {
        $count = User::updateAll(
            ['total_time_today' => 0],
            ['>', 'total_time_today', 0]
        );

        Yii::info("Daily activity reset for {$count} users", __METHOD__);
    }
}