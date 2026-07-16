<?php

namespace common\services;

use Yii;
use common\models\Reminders;
use backend\models\User;
use backend\models\RemindersUsersModel;
use backend\models\NotificationModel;
use common\services\CourierCleanupService;
use common\models\Tasks;
use common\models\Packages;

/**
 * Service for handling reminder processing
 */
class RemindersService
{
    /**
     * Process REM1 reminder
     * Sent 10 minutes after the first email. Condition - candidate did not log into personal account
     */
    public function processREM1()
    {
        $tenMinutesAgo = time() - 600; // 10 minutes in seconds

        // Get REM1 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM1']);
        if (!$reminderTemplate) {
            Yii::error("REM1 reminder template not found");
            return;
        }

        // Find users with first notification more than 10 minutes ago
        // who haven't logged in and are couriers with waiting status
        $usersWithFirstNotification = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('notification n', 'n.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM1\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_WAITING_FOR_CALL,
                'ru.id' => null // No REM1 reminder exists yet
            ])
            ->andWhere(['<=', 'n.created_at', $tenMinutesAgo])
            ->andWhere([
                'or',
                ['u.last_activity' => 0],
                'u.last_activity < n.created_at'
            ])
            ->andWhere([
                'n.id' => new \yii\db\Expression('(SELECT MIN(id) FROM notification WHERE user_id = u.id)')
            ])
            ->all();

        if (empty($usersWithFirstNotification)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($usersWithFirstNotification as $user) {

            try {
                $remindersUsersService->createReminder(
                    $user->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM1 reminder created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM1 reminder for user {$user->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM1 processing completed. Processed {$processedCount} users.");
    }

    /**
     * Process REM2 reminder
     * Sent 3 hours after REM1. Condition - candidate did not log into personal account after REM1
     */
    public function processREM2()
    {
        $threeHoursAgo = time() - 10800; // 3 hours in seconds

        // Get REM2 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM2']);
        if (!$reminderTemplate) {
            Yii::error("REM2 reminder template not found");
            return;
        }

        // Find users who received REM1 more than 3 hours ago
        // and still haven't logged in after REM1
        $usersWithRem1 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru1', 'ru1.user_id = u.id')
            ->leftJoin('reminders_users ru2', 'ru2.user_id = u.id AND ru2.reminder_code = \'REM2\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_WAITING_FOR_CALL,
                'ru1.reminder_code' => 'REM1',
                'ru2.id' => null // No REM2 reminder exists yet
            ])
            ->andWhere(['<=', 'ru1.created_at', $threeHoursAgo])
            ->andWhere([
                'or',
                ['u.last_activity' => 0],
                'u.last_activity < ru1.created_at'
            ])
            ->all();

        if (empty($usersWithRem1)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($usersWithRem1 as $user) {

            try {
                $remindersUsersService->createReminder(
                    $user->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM2 reminder created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM2 reminder for user {$user->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM2 processing completed. Processed {$processedCount} users.");
    }

    /**
     * Process REM3 reminder
     * Sent during test stage. If within 10 hours after status change to "ready to take test" there is no confirmation that test was passed
     */
    public function processREM3()
    {
        $tenHoursAgo = time() - 36000; // 10 hours in seconds

        // Get REM3 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM3']);
        if (!$reminderTemplate) {
            Yii::error("REM3 reminder template not found");
            return;
        }

        // Find users who are in TAKING_TEST status for more than 10 hours
        // and haven't received REM3 yet
        $usersInTakingTest = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM3\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TAKING_TEST,
                'ru.id' => null // No REM3 reminder exists yet
            ])
            ->andWhere(['<=', 'u.substatus_changed_at', $tenHoursAgo])
            ->andWhere(['>', 'u.substatus_changed_at', 0]) // Ensure substatus_changed_at is set
            ->all();

        if (empty($usersInTakingTest)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($usersInTakingTest as $user) {
            try {
                $remindersUsersService->createReminder(
                    $user->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM3 reminder created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM3 reminder for user {$user->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM3 processing completed. Processed {$processedCount} users.");
    }

    /**
     * Process REM4 reminder
     * Sent 48 hours after REM3. Same conditions as REM3. If no changes after REM4 - candidate data is deleted from database
     */
    public function processREM4()
    {
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds
        $hourAgo24 = time() - 24*3600; // 24 hour in seconds

        // Get REM4 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM4']);
        if (!$reminderTemplate) {
            Yii::error("REM4 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM4 more than 24 hour ago and still haven't passed test
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru4', 'ru4.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TAKING_TEST,
                'ru4.reminder_code' => 'REM4'
            ])
            ->andWhere(['<=', 'ru4.created_at', $hourAgo24])
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM4 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM4 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM4: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM4 to couriers who received REM3 more than 48 hours ago
        $couriersWithRem3 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru3', 'ru3.user_id = u.id')
            ->leftJoin('reminders_users ru4', 'ru4.user_id = u.id AND ru4.reminder_code = \'REM4\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TAKING_TEST,
                'ru3.reminder_code' => 'REM3',
                'ru4.id' => null // No REM4 reminder exists yet
            ])
            ->andWhere(['<=', 'ru3.created_at', $fortyEightHoursAgo])
            ->all();

        if (empty($couriersWithRem3)) {
            Yii::info("REM4 processing completed. No couriers to process for REM4 sending.");
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem3 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM4 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM4 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM4 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM5 reminder
     * Sent during greeting stage. If welcome call goes to voicemail - CC employee sets appropriate status and candidate receives reminder
     */
    public function processREM5()
    {
        // Get REM5 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM5']);
        if (!$reminderTemplate) {
            Yii::error("REM5 reminder template not found");
            return;
        }

        // Find couriers who have SUBSTATUS_CC_IN_VOICE status and haven't received REM5 yet
        // This status is set by CC employee when welcome call goes to voicemail
        $couriersForRem5 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru5', 'ru5.user_id = u.id AND ru5.reminder_code = \'REM5\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_IN_VOICE,
                'ru5.id' => null // No REM5 reminder exists yet
            ])
            ->all();

        if (empty($couriersForRem5)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersForRem5 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM5 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM5 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM5 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM6 reminder
     * Sent after 24 hours. If status does not change within 24 hours after REM5 - send reminder. If status does not change 24 hours after REM6 - delete candidate from database
     */
    public function processREM6()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds

        // Get REM6 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM6']);
        if (!$reminderTemplate) {
            Yii::error("REM6 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM6 more than 24 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru6', 'ru6.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_IN_VOICE,
                'ru6.reminder_code' => 'REM6'
            ])
            ->andWhere(['<=', 'ru6.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru6.created_at']) // Status didn't change after REM6
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM6 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM6 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM6: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM6 to couriers who received REM5 more than 24 hours ago and status hasn't changed
        $couriersWithRem5 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru5', 'ru5.user_id = u.id')
            ->leftJoin('reminders_users ru6', 'ru6.user_id = u.id AND ru6.reminder_code = \'REM6\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_IN_VOICE,
                'ru5.reminder_code' => 'REM5',
                'ru6.id' => null // No REM6 reminder exists yet
            ])
            ->andWhere(['<=', 'ru5.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru5.created_at']) // Status didn't change after REM5
            ->all();

        if (empty($couriersWithRem5)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem5 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM6 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM6 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM6 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM7 reminder
     * Sent during greeting stage. If welcome call failed because wrong number was provided - CC employee sets status and reminder is sent immediately
     */
    public function processREM7()
    {
        // Get REM7 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM7']);
        if (!$reminderTemplate) {
            Yii::error("REM7 reminder template not found");
            return;
        }

        // Find couriers who have SUBSTATUS_CC_WRONG_NUMBER status and haven't received REM7 yet
        $couriersForRem7 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru7', 'ru7.user_id = u.id AND ru7.reminder_code = \'REM7\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_WRONG_NUMBER,
                'ru7.id' => null // No REM7 reminder exists yet
            ])
            ->all();

        if (empty($couriersForRem7)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersForRem7 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM7 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM7 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM7 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM8 reminder
     * Sent after 5 hours. If 5 hours after REM7 CC status does not change - send reminder
     */
    public function processREM8()
    {
        $fiveHoursAgo = time() - 18000; // 5 hours in seconds

        // Get REM8 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM8']);
        if (!$reminderTemplate) {
            Yii::error("REM8 reminder template not found");
            return;
        }

        // Send REM8 to couriers who received REM7 more than 5 hours ago and status hasn't changed
        $couriersWithRem7 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru7', 'ru7.user_id = u.id')
            ->leftJoin('reminders_users ru8', 'ru8.user_id = u.id AND ru8.reminder_code = \'REM8\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_WRONG_NUMBER,
                'ru7.reminder_code' => 'REM7',
                'ru8.id' => null // No REM8 reminder exists yet
            ])
            ->andWhere(['<=', 'ru7.created_at', $fiveHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru7.created_at']) // Status didn't change after REM7
            ->all();

        if (empty($couriersWithRem7)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem7 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM8 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM8 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM8 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM9 reminder
     * Sent after 24 hours. Same marker and conditions as REM8. If status does not change after 48 hours - delete candidate from database
     */
    public function processREM9()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds

        // Get REM9 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM9']);
        if (!$reminderTemplate) {
            Yii::error("REM9 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM9 more than 48 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru9', 'ru9.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_WRONG_NUMBER,
                'ru9.reminder_code' => 'REM9'
            ])
            ->andWhere(['<=', 'ru9.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru9.created_at']) // Status didn't change after REM9
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM9 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM9 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM9: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM9 to couriers who received REM8 more than 24 hours ago and status hasn't changed
        $couriersWithRem8 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru8', 'ru8.user_id = u.id')
            ->leftJoin('reminders_users ru9', 'ru9.user_id = u.id AND ru9.reminder_code = \'REM9\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CC_WRONG_NUMBER,
                'ru8.reminder_code' => 'REM8',
                'ru9.id' => null // No REM9 reminder exists yet
            ])
            ->andWhere(['<=', 'ru8.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru8.created_at']) // Status didn't change after REM8
            ->all();

        if (empty($couriersWithRem8)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem8 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM9 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM9 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM9 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM10 reminder
     * Sent during greeting stage. If CC set status unavailable/busy/no voice. Sent 1 hour after setting appropriate CC status
     */
    public function processREM10()
    {
        $oneHourAgo = time() - 3600; // 1 hour in seconds

        // Get REM10 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM10']);
        if (!$reminderTemplate) {
            Yii::error("REM10 reminder template not found");
            return;
        }

        // Find couriers who have specific statuses set more than 1 hour ago and haven't received REM10 yet
        $couriersForRem10 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru10', 'ru10.user_id = u.id AND ru10.reminder_code = \'REM10\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'ru10.id' => null // No REM10 reminder exists yet
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_CC_UNAVAILABLE,
                User::SUBSTATUS_CC_HUNG_UP,
                User::SUBSTATUS_CC_VOICE_FULL
            ]])
            ->andWhere(['<=', 'u.substatus_changed_at', $oneHourAgo])
            ->all();

        if (empty($couriersForRem10)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersForRem10 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM10 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM10 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM10 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM11 reminder
     * Sent after 24 hours. Same marker as REM10. If status does not change 24 hours after REM10 - send reminder. If status does not change 24 hours after REM11 - delete candidate from database
     */
    public function processREM11()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds

        // Get REM11 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM11']);
        if (!$reminderTemplate) {
            Yii::error("REM11 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM11 more than 24 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru11', 'ru11.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'ru11.reminder_code' => 'REM11'
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_CC_UNAVAILABLE,
                User::SUBSTATUS_CC_HUNG_UP,
                User::SUBSTATUS_CC_VOICE_FULL
            ]])
            ->andWhere(['<=', 'ru11.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru11.created_at']) // Status didn't change after REM11
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM11 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM11 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM11: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM11 to couriers who received REM10 more than 24 hours ago and status hasn't changed
        $couriersWithRem10 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru10', 'ru10.user_id = u.id')
            ->leftJoin('reminders_users ru11', 'ru11.user_id = u.id AND ru11.reminder_code = \'REM11\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'ru10.reminder_code' => 'REM10',
                'ru11.id' => null // No REM11 reminder exists yet
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_CC_UNAVAILABLE,
                User::SUBSTATUS_CC_HUNG_UP,
                User::SUBSTATUS_CC_VOICE_FULL
            ]])
            ->andWhere(['<=', 'ru10.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru10.created_at']) // Status didn't change after REM10
            ->all();

        if (empty($couriersWithRem10)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem10 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM11 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM11 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM11 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM12 reminder
     * Sent after 24 hours. If 24 hours after setting appropriate status candidate took no action - send reminder
     */
    public function processREM12()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds

        // Get REM12 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM12']);
        if (!$reminderTemplate) {
            Yii::error("REM12 reminder template not found");
            return;
        }

        // Find couriers who have SUBSTATUS_WRONG_NUMBER status set more than 24 hours ago
        // and haven't received REM12 yet and status hasn't changed
        $couriersForRem12 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru12', 'ru12.user_id = u.id AND ru12.reminder_code = \'REM12\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_WRONG_NUMBER,
                'ru12.id' => null // No REM12 reminder exists yet
            ])
            ->andWhere(['<=', 'u.substatus_changed_at', $twentyFourHoursAgo])
            ->all();

        if (empty($couriersForRem12)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersForRem12 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM12 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM12 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM12 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM13 reminder
     * Sent 24 hours after REM12. If after REM12 within 24 hours CC did not change status - send reminder, delete from database after 48 hours
     */
    public function processREM13()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds

        // Get REM13 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM13']);
        if (!$reminderTemplate) {
            Yii::error("REM13 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM13 more than 48 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru13', 'ru13.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_WRONG_NUMBER,
                'ru13.reminder_code' => 'REM13'
            ])
            ->andWhere(['<=', 'ru13.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru13.created_at']) // Status didn't change after REM13
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM13 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM13 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM13: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM13 to couriers who received REM12 more than 24 hours ago and status hasn't changed
        $couriersWithRem12 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru12', 'ru12.user_id = u.id')
            ->leftJoin('reminders_users ru13', 'ru13.user_id = u.id AND ru13.reminder_code = \'REM13\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_WRONG_NUMBER,
                'ru12.reminder_code' => 'REM12',
                'ru13.id' => null // No REM13 reminder exists yet
            ])
            ->andWhere(['<=', 'ru12.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru12.created_at']) // Status didn't change after REM12
            ->all();

        if (empty($couriersWithRem12)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem12 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM13 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM13 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM13 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM14 reminder
     * Sent when status is set. If CC sets appropriate status ("voicemail full") - send reminder
     */
    public function processREM14()
    {
        // Get REM14 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM14']);
        if (!$reminderTemplate) {
            Yii::error("REM14 reminder template not found");
            return;
        }

        $couriersForRem14 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru14', 'ru14.user_id = u.id AND ru14.reminder_code = \'REM14\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_VOICE_FULL,
                'ru14.id' => null // No REM14 reminder exists yet
            ])
            ->all();

        if (empty($couriersForRem14)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersForRem14 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM14 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM14 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM14 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM15 reminder
     * Sent 24 hours after REM14. If after REM14 within 24 hours candidate takes no action - send reminder, delete candidate data from database after 48 hours
     */
    public function processREM15()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds

        // Get REM15 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM15']);
        if (!$reminderTemplate) {
            Yii::error("REM15 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM15 more than 48 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru15', 'ru15.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_VOICE_FULL,
                'ru15.reminder_code' => 'REM15'
            ])
            ->andWhere(['<=', 'ru15.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru15.created_at']) // Status didn't change after REM15
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM15 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM15 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM15: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM15 to couriers who received REM14 more than 24 hours ago and status hasn't changed
        $couriersWithRem14 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru14', 'ru14.user_id = u.id')
            ->leftJoin('reminders_users ru15', 'ru15.user_id = u.id AND ru15.reminder_code = \'REM15\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_VOICE_FULL,
                'ru14.reminder_code' => 'REM14',
                'ru15.id' => null // No REM15 reminder exists yet
            ])
            ->andWhere(['<=', 'ru14.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru14.created_at']) // Status didn't change after REM14
            ->all();

        if (empty($couriersWithRem14)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem14 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM15 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM15 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM15 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM16 reminder
     * Sent during signing stage, 10 minutes after notification that contract is available in personal account
     */
    public function processREM16()
    {
        $tenMinutesAgo = time() - 600; // 10 minutes in seconds

        // Get REM16 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM16']);
        if (!$reminderTemplate) {
            Yii::error("REM16 reminder template not found");
            return;
        }

        // Find couriers who got SUBSTATUS_INTERVIEWED status more than 10 minutes ago
        // and haven't signed the contract yet and haven't received REM16 yet
        // Contract notification is sent automatically when status changes to SUBSTATUS_INTERVIEWED
        $couriersWithInterviewedStatus = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru16', 'ru16.user_id = u.id AND ru16.reminder_code = \'REM16\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_INTERVIEWED, // Status when contract becomes available
                'ru16.id' => null // No REM16 reminder exists yet
            ])
            ->andWhere(['<=', 'u.substatus_changed_at', $tenMinutesAgo])
            ->all();

        if (empty($couriersWithInterviewedStatus)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithInterviewedStatus as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM16 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM16 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM16 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM17 reminder
     * Sent after 24 hours. If after REM16 within 24 hours candidate status does not change - send reminder
     */
    public function processREM17()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds

        // Get REM17 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM17']);
        if (!$reminderTemplate) {
            Yii::error("REM17 reminder template not found");
            return;
        }

        // Send REM17 to couriers who received REM16 more than 24 hours ago and status hasn't changed
        $couriersWithRem16 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru16', 'ru16.user_id = u.id')
            ->leftJoin('reminders_users ru17', 'ru17.user_id = u.id AND ru17.reminder_code = \'REM17\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_INTERVIEWED, // Still not signed contract
                'ru16.reminder_code' => 'REM16',
                'ru17.id' => null // No REM17 reminder exists yet
            ])
            ->andWhere(['<=', 'ru16.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru16.created_at']) // Status didn't change after REM16
            ->all();

        if (empty($couriersWithRem16)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem16 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM17 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM17 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM17 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM18 reminder
     * Sent after 24 hours. If after REM17 within 24 hours candidate status does not change - send reminder, delete candidate from database 48 hours after REM18
     */
    public function processREM18()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds

        // Get REM18 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM18']);
        if (!$reminderTemplate) {
            Yii::error("REM18 reminder template not found");
            return;
        }

        // STEP 1: Delete couriers who received REM18 more than 48 hours ago and status hasn't changed
        $couriersToDelete = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru18', 'ru18.user_id = u.id')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_INTERVIEWED, // Still not signed contract
                'ru18.reminder_code' => 'REM18'
            ])
            ->andWhere(['<=', 'ru18.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru18.created_at']) // Status didn't change after REM18
            ->all();

        if (!empty($couriersToDelete)) {
            $courierCleanupService = new CourierCleanupService();

            foreach ($couriersToDelete as $courier) {
                try {
                    if ($courierCleanupService->deleteCourierCompletely($courier->id)) {
                        Yii::info("Courier ID {$courier->id} completely deleted after REM18 timeout");
                    } else {
                        Yii::error("Failed to completely delete courier ID {$courier->id} after REM18 timeout");
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception during complete deletion of courier {$courier->id} after REM18: " . $e->getMessage());
                }
            }
        }

        // STEP 2: Send REM18 to couriers who received REM17 more than 24 hours ago and status hasn't changed
        $couriersWithRem17 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru17', 'ru17.user_id = u.id')
            ->leftJoin('reminders_users ru18', 'ru18.user_id = u.id AND ru18.reminder_code = \'REM18\'')
            ->where([
                'aa.item_name' => 'courier',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_INTERVIEWED, // Still not signed contract
                'ru17.reminder_code' => 'REM17',
                'ru18.id' => null // No REM18 reminder exists yet
            ])
            ->andWhere(['<=', 'ru17.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru17.created_at']) // Status didn't change after REM17
            ->all();

        if (empty($couriersWithRem17)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($couriersWithRem17 as $courier) {
            try {
                $remindersUsersService->createReminder(
                    $courier->id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM18 reminder created for courier ID: {$courier->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM18 reminder for courier {$courier->id}: " . $e->getMessage());
            }
        }

        Yii::info("REM18 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM19 reminder
     * Sent during first package receipt stage. If 6 hours after first package delivery to courier (trackable by tracking number) he does not change package status/mark successful receipt - send reminder
     */
    public function processREM19()
    {
        $sixHoursAgo = time() - 21600; // 6 hours in seconds

        // Get REM19 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM19']);
        if (!$reminderTemplate) {
            Yii::error("REM19 reminder template not found");
            return;
        }

        // Find first packages for couriers that were delivered more than 6 hours ago
        // but courier hasn't marked them as received (status still NEW)
        $packagesForRem19 = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->leftJoin('reminders_users ru19', 'ru19.user_id = p.courier_id AND ru19.reminder_code = \'REM19\'')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_NEW,
                'ru19.id' => null // No REM19 reminder exists yet for this courier
            ])
            ->andWhere(['not', ['p.track_status_update' => null]]) // Package has tracking update (delivered)
            ->andWhere(['<=', 'p.track_status_update', $sixHoursAgo])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        if (empty($packagesForRem19)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($packagesForRem19 as $package) {
            try {
                $remindersUsersService->createReminder(
                    $package->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM19 reminder created for courier ID: {$package->courier_id}, package ID: {$package->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM19 reminder for courier {$package->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM19 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM20 reminder
     * Sent after 24 hours. If after REM19 within 24 hours courier did not mark package receipt - send reminder
     * If 5 hours after REM20 courier doesn't mark package receipt - add automatic comment to package
     */
    public function processREM20()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fiveHoursAgo = time() - 18000; // 5 hours in seconds

        // Get REM20 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM20']);
        if (!$reminderTemplate) {
            Yii::error("REM20 reminder template not found");
            return;
        }

        // STEP 1: Add automatic comment to packages where REM20 was sent more than 5 hours ago
        // and courier still hasn't marked package receipt
        $packagesForComment = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->innerJoin('reminders_users ru20', 'ru20.user_id = p.courier_id')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_IN_PROGRESS,
                'ru20.reminder_code' => 'REM20'
            ])
            ->andWhere(['<=', 'ru20.created_at', $fiveHoursAgo])
            ->andWhere(['or', ['p.comment' => null], ['not like', 'p.comment', '%Automatic comment: Courier has not confirmed package receipt%']])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        foreach ($packagesForComment as $package) {
            try {
                //TODO: Add automatic comment and urgent call notification for packages
                // where REM20 was sent more than 5 hours ago and courier still hasn't marked package receipt

            } catch (\Exception $e) {
                Yii::error("Error adding automatic comment to package {$package->id}: " . $e->getMessage());
            }
        }

        // STEP 2: Send REM20 to couriers who received REM19 more than 24 hours ago and haven't marked receipt
        $packagesWithRem19 = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->innerJoin('reminders_users ru19', 'ru19.user_id = p.courier_id')
            ->leftJoin('reminders_users ru20', 'ru20.user_id = p.courier_id AND ru20.reminder_code = \'REM20\'')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_IN_PROGRESS,
                'ru19.reminder_code' => 'REM19',
                'ru20.id' => null // No REM20 reminder exists yet
            ])
            ->andWhere(['<=', 'ru19.created_at', $twentyFourHoursAgo])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        if (empty($packagesWithRem19)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($packagesWithRem19 as $package) {
            try {
                $remindersUsersService->createReminder(
                    $package->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM20 reminder created for courier ID: {$package->courier_id}, package ID: {$package->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM20 reminder for courier {$package->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM20 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM21 reminder
     * Sent after receiving first package. If courier after receiving package within 6 hours did not follow instructions and change package status - send reminder
     */
    public function processREM21()
    {
        $sixHoursAgo = time() - 21600; // 6 hours in seconds

        // Get REM21 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM21']);
        if (!$reminderTemplate) {
            Yii::error("REM21 reminder template not found");
            return;
        }

        // Find first packages for couriers that are IN_PROGRESS for more than 6 hours
        // but courier hasn't followed instructions and changed package status
        $packagesForRem21 = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->leftJoin('reminders_users ru21', 'ru21.user_id = p.courier_id AND ru21.reminder_code = \'REM21\'')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_IN_PROGRESS,
                'ru21.id' => null // No REM21 reminder exists yet for this courier
            ])
            ->andWhere(['not', ['p.track_status_update' => null]]) // Package has tracking update
            ->andWhere(['<=', 'p.track_status_update', $sixHoursAgo])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        if (empty($packagesForRem21)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($packagesForRem21 as $package) {
            try {
                $remindersUsersService->createReminder(
                    $package->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM21 reminder created for courier ID: {$package->courier_id}, package ID: {$package->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM21 reminder for courier {$package->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM21 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM22 reminder
     * Sent after 24 hours. If after REM21 courier did not follow instructions and change package status - send reminder
     */
    public function processREM22()
    {
        $twentyFourHoursAgo = time() - 86400; // 24 hours in seconds
        $fiveHoursAgo = time() - 18000; // 5 hours in seconds

        // Get REM22 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM22']);
        if (!$reminderTemplate) {
            Yii::error("REM22 reminder template not found");
            return;
        }

        // STEP 1: Add automatic comment to packages where REM22 was sent more than 5 hours ago
        // and courier still hasn't changed package status
        $packagesForComment = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->innerJoin('reminders_users ru22', 'ru22.user_id = p.courier_id')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_IN_PROGRESS,
                'ru22.reminder_code' => 'REM22'
            ])
            ->andWhere(['<=', 'ru22.created_at', $fiveHoursAgo])
            ->andWhere(['or', ['p.comment' => null], ['not like', 'p.comment', '%Automatic comment: Courier has not followed instructions%']])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        foreach ($packagesForComment as $package) {
            try {
                //TODO: Add automatic comment and urgent call notification for packages
                // where REM22 was sent more than 5 hours ago and courier still hasn't changed package status
            } catch (\Exception $e) {
                Yii::error("Error adding automatic comment to package {$package->id}: " . $e->getMessage());
            }
        }

        // STEP 2: Send REM22 to couriers who received REM21 more than 24 hours ago and haven't changed package status
        $packagesWithRem21 = Packages::find()
            ->alias('p')
            ->innerJoin('auth_assignment aa', 'aa.user_id = p.courier_id')
            ->innerJoin('reminders_users ru21', 'ru21.user_id = p.courier_id')
            ->leftJoin('reminders_users ru22', 'ru22.user_id = p.courier_id AND ru22.reminder_code = \'REM22\'')
            ->where([
                'aa.item_name' => 'courier',
                'p.status' => Packages::STATUS_IN_PROGRESS,
                'ru21.reminder_code' => 'REM21',
                'ru22.id' => null // No REM22 reminder exists yet
            ])
            ->andWhere(['<=', 'ru21.created_at', $twentyFourHoursAgo])
            ->andWhere([
                'p.id' => new \yii\db\Expression('(SELECT MIN(id) FROM packages WHERE courier_id = p.courier_id)')
            ]) // Only first package for each courier
            ->all();

        if (empty($packagesWithRem21)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($packagesWithRem21 as $package) {
            try {
                $remindersUsersService->createReminder(
                    $package->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM22 reminder created for courier ID: {$package->courier_id}, package ID: {$package->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM22 reminder for courier {$package->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM22 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM23 reminder
     * Sent during package sending task execution stage. If within 6 hours after loading label and receiving sending instructions courier does not change task status - send reminder
     */
    public function processREM23()
    {
        $sixHoursAgo = time() - 21600; // 6 hours in seconds

        // Get REM23 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM23']);
        if (!$reminderTemplate) {
            Yii::error("REM23 reminder template not found");
            return;
        }

        // Find tasks for couriers that have label loaded and instructions received more than 6 hours ago
        // but courier hasn't changed task status (status still NEW)
        $tasksForRem23 = Tasks::find()
            ->alias('t')
            ->innerJoin('auth_assignment aa', 'aa.user_id = t.courier_id')
            ->leftJoin('reminders_users ru23', 'ru23.user_id = t.courier_id AND ru23.reminder_code = \'REM23\'')
            ->where([
                'aa.item_name' => 'courier',
                't.status' => Tasks::STATUS_NEW,
                'ru23.id' => null // No REM23 reminder exists yet for this courier
            ])
            ->andWhere(['not', ['t.track_status_update' => null]]) // Task has tracking update (label loaded)
            ->andWhere(['<=', 't.track_status_update', $sixHoursAgo])
            ->all();

        if (empty($tasksForRem23)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($tasksForRem23 as $task) {
            try {
                $remindersUsersService->createReminder(
                    $task->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM23 reminder created for courier ID: {$task->courier_id}, task ID: {$task->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM23 reminder for courier {$task->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM23 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Process REM24 reminder
     * Sent after 48 hours. If 48 hours after REM23 courier did not follow sending instructions and change task status - send reminder
     */
    public function processREM24()
    {
        $fortyEightHoursAgo = time() - 172800; // 48 hours in seconds
        $fiveHoursAgo = time() - 18000; // 5 hours in seconds

        // Get REM24 reminder template once
        $reminderTemplate = Reminders::findOne(['code' => 'REM24']);
        if (!$reminderTemplate) {
            Yii::error("REM24 reminder template not found");
            return;
        }

        // STEP 1: Add automatic comment to tasks where REM24 was sent more than 5 hours ago
        // and courier still hasn't changed task status
        $tasksForComment = Tasks::find()
            ->alias('t')
            ->innerJoin('auth_assignment aa', 'aa.user_id = t.courier_id')
            ->innerJoin('reminders_users ru24', 'ru24.user_id = t.courier_id')
            ->where([
                'aa.item_name' => 'courier',
                't.status' => Tasks::STATUS_NEW,
                'ru24.reminder_code' => 'REM24'
            ])
            ->andWhere(['<=', 'ru24.created_at', $fiveHoursAgo])
            ->andWhere(['or', ['t.comment' => null], ['not like', 't.comment', '%Automatic comment: Courier has not followed sending instructions%']])
            ->all();

        foreach ($tasksForComment as $task) {
            try {
                //TODO: Add automatic comment and urgent call notification for tasks
                // where REM24 was sent more than 5 hours ago and courier still hasn't changed task status
            } catch (\Exception $e) {
                Yii::error("Error adding automatic comment to task {$task->id}: " . $e->getMessage());
            }
        }

        // STEP 2: Send REM24 to couriers who received REM23 more than 48 hours ago and haven't changed task status
        $tasksWithRem23 = Tasks::find()
            ->alias('t')
            ->innerJoin('auth_assignment aa', 'aa.user_id = t.courier_id')
            ->innerJoin('reminders_users ru23', 'ru23.user_id = t.courier_id')
            ->leftJoin('reminders_users ru24', 'ru24.user_id = t.courier_id AND ru24.reminder_code = \'REM24\'')
            ->where([
                'aa.item_name' => 'courier',
                't.status' => Tasks::STATUS_NEW,
                'ru23.reminder_code' => 'REM23',
                'ru24.id' => null // No REM24 reminder exists yet
            ])
            ->andWhere(['<=', 'ru23.created_at', $fortyEightHoursAgo])
            ->all();

        if (empty($tasksWithRem23)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();
        $processedCount = 0;

        foreach ($tasksWithRem23 as $task) {
            try {
                $remindersUsersService->createReminder(
                    $task->courier_id,
                    $reminderTemplate->id,
                    true // Send email
                );

                $processedCount++;
                Yii::info("REM24 reminder created for courier ID: {$task->courier_id}, task ID: {$task->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM24 reminder for courier {$task->courier_id}: " . $e->getMessage());
            }
        }

        Yii::info("REM24 processing completed. Processed {$processedCount} couriers.");
    }

    /**
     * Create reminder for user
     *
     * @param string $reminderCode Reminder code (REM1-REM24)
     * @param int $userId User ID
     * @param string|null $customText Custom text for reminder (optional)
     * @return bool Success status
     */
    protected function createReminderForUser($reminderCode, $userId, $customText = null)
    {
        // Get reminder from database
        $reminder = Reminders::findOne(['code' => $reminderCode]);
        if (!$reminder) {
            return false;
        }

        // Check if reminder already exists for this user
        $existingReminder = RemindersUsersModel::findOne([
            'reminder_code' => $reminderCode,
            'user_id' => $userId
        ]);

        if ($existingReminder) {
            return false; // Already exists
        }

        // Create new reminder for user
        $reminderUser = new RemindersUsersModel();
        $reminderUser->reminder_id = $reminder->id;
        $reminderUser->reminder_code = $reminderCode;
        $reminderUser->user_id = $userId;
        $reminderUser->text = $customText ?? $reminder->text;
        $reminderUser->read = 0;
        $reminderUser->created_at = time();

        return $reminderUser->save();
    }

    /**
     * Send email notification (placeholder method)
     *
     * @param int $userId User ID
     * @param string $subject Email subject
     * @param string $message Email message
     * @return bool Success status
     */
    protected function sendEmailNotification($userId, $subject, $message)
    {
        // TODO: Implement email sending logic
        return true;
    }
}