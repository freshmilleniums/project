<?php

namespace common\services;

use Yii;
use common\models\Reminders;
use backend\models\User;
use backend\models\RemindersUsersModel;
use backend\models\NotificationModel;

/**
 * Service for handling reminder processing
 */
class RemindersService
{
    /**
     * Process REM1 reminder
     * Sent 10 minutes after welcome notification. Condition - employee did not log into personal account
     */
    public function processREM1()
    {
        $tenMinutesAgo = time() - 600;

        $reminderTemplate = Reminders::findOne(['code' => 'REM1']);
        if (!$reminderTemplate) {
            Yii::error("REM1 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('notification n', 'n.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM1\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_NEW_APPLICANT,
                'ru.id' => null,
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

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM1 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM1 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM2 reminder
     * Sent 24 hours after REM1. Condition - employee still has substatus NEW_APPLICANT
     */
    public function processREM2()
    {
        $twentyFourHoursAgo = time() - 86400;

        $reminderTemplate = Reminders::findOne(['code' => 'REM2']);
        if (!$reminderTemplate) {
            Yii::error("REM2 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru1', 'ru1.user_id = u.id')
            ->leftJoin('reminders_users ru2', 'ru2.user_id = u.id AND ru2.reminder_code = \'REM2\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_NEW_APPLICANT,
                'ru1.reminder_code' => 'REM1',
                'ru2.id' => null,
            ])
            ->andWhere(['<=', 'ru1.created_at', $twentyFourHoursAgo])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                // After REM2 - archive employee
                User::updateAll(
                    ['substatus' => User::SUBSTATUS_ARCHIVED, 'substatus_changed_at' => time()],
                    ['id' => $user->id]
                );
                Yii::info("REM2 created and employee {$user->id} archived");
            } catch (\Exception $e) {
                Yii::error("Error creating REM2 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM3 reminder
     * Sent 10 minutes after substatus changed to CONTRACT_SENT. Condition - contract not signed
     */
    public function processREM3()
    {
        $tenMinutesAgo = time() - 600;

        $reminderTemplate = Reminders::findOne(['code' => 'REM3']);
        if (!$reminderTemplate) {
            Yii::error("REM3 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM3\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CONTRACT_SENT,
                'ru.id' => null,
            ])
            ->andWhere(['<=', 'u.substatus_changed_at', $tenMinutesAgo])
            ->andWhere(['>', 'u.substatus_changed_at', 0])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM3 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM3 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM4 reminder
     * Sent 24 hours after REM3. Condition - employee still has substatus CONTRACT_SENT
     */
    public function processREM4()
    {
        $twentyFourHoursAgo = time() - 86400;

        $reminderTemplate = Reminders::findOne(['code' => 'REM4']);
        if (!$reminderTemplate) {
            Yii::error("REM4 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru3', 'ru3.user_id = u.id')
            ->leftJoin('reminders_users ru4', 'ru4.user_id = u.id AND ru4.reminder_code = \'REM4\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CONTRACT_SENT,
                'ru3.reminder_code' => 'REM3',
                'ru4.id' => null,
            ])
            ->andWhere(['<=', 'ru3.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru3.created_at'])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM4 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM4 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM5 reminder
     * Sent 48 hours after REM4. Contract still not signed - employee moved to ARCHIVED
     */
    public function processREM5()
    {
        $fortyEightHoursAgo = time() - 172800;

        $reminderTemplate = Reminders::findOne(['code' => 'REM5']);
        if (!$reminderTemplate) {
            Yii::error("REM5 reminder template not found");
            return;
        }

        // STEP 1: Archive employees who received REM5 more than 48 hours ago and still CONTRACT_SENT
        $usersToArchive = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru5', 'ru5.user_id = u.id')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CONTRACT_SENT,
                'ru5.reminder_code' => 'REM5',
            ])
            ->andWhere(['<=', 'ru5.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru5.created_at'])
            ->all();

        foreach ($usersToArchive as $user) {
            try {
                User::updateAll(
                    ['substatus' => User::SUBSTATUS_ARCHIVED, 'substatus_changed_at' => time()],
                    ['id' => $user->id]
                );
                Yii::info("Employee {$user->id} archived after REM5 timeout");
            } catch (\Exception $e) {
                Yii::error("Error archiving user {$user->id} after REM5: " . $e->getMessage());
            }
        }

        // STEP 2: Send REM5 to employees who received REM4 more than 48 hours ago
        $usersWithRem4 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru4', 'ru4.user_id = u.id')
            ->leftJoin('reminders_users ru5', 'ru5.user_id = u.id AND ru5.reminder_code = \'REM5\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_CONTRACT_SENT,
                'ru4.reminder_code' => 'REM4',
                'ru5.id' => null,
            ])
            ->andWhere(['<=', 'ru4.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru4.created_at'])
            ->all();

        if (empty($usersWithRem4)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($usersWithRem4 as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM5 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM5 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM6 reminder
     * Sent 10 hours after last training activity. Condition - training module not completed
     */
    public function processREM6()
    {
        $tenHoursAgo = time() - 36000;

        $reminderTemplate = Reminders::findOne(['code' => 'REM6']);
        if (!$reminderTemplate) {
            Yii::error("REM6 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('user_training_progress utp', 'utp.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM6\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TRAINING_IN_PROGRESS,
                'ru.id' => null,
            ])
            ->andWhere(['<=', 'utp.updated_at', $tenHoursAgo])
            ->andWhere(['>', 'utp.updated_at', 0])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM6 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM6 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM7 reminder
     * Sent 48 hours after REM6. Same conditions. If no changes after REM7 - employee is archived
     */
    public function processREM7()
    {
        $fortyEightHoursAgo = time() - 172800;

        $reminderTemplate = Reminders::findOne(['code' => 'REM7']);
        if (!$reminderTemplate) {
            Yii::error("REM7 reminder template not found");
            return;
        }

        // STEP 1: Archive employees who received REM7 more than 48 hours ago and training still not progressed
        $usersToArchive = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('user_training_progress utp', 'utp.user_id = u.id')
            ->innerJoin('reminders_users ru7', 'ru7.user_id = u.id')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TRAINING_IN_PROGRESS,
                'ru7.reminder_code' => 'REM7',
            ])
            ->andWhere(['<=', 'ru7.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'utp.updated_at', 'ru7.created_at'])
            ->all();

        foreach ($usersToArchive as $user) {
            try {
                User::updateAll(
                    ['substatus' => User::SUBSTATUS_ARCHIVED, 'substatus_changed_at' => time()],
                    ['id' => $user->id]
                );
                Yii::info("Employee {$user->id} archived after REM7 timeout");
            } catch (\Exception $e) {
                Yii::error("Error archiving user {$user->id} after REM7: " . $e->getMessage());
            }
        }

        // STEP 2: Send REM7 to employees who received REM6 more than 48 hours ago and training still not progressed
        $usersWithRem6 = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('user_training_progress utp', 'utp.user_id = u.id')
            ->innerJoin('reminders_users ru6', 'ru6.user_id = u.id')
            ->leftJoin('reminders_users ru7', 'ru7.user_id = u.id AND ru7.reminder_code = \'REM7\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'u.substatus' => User::SUBSTATUS_TRAINING_IN_PROGRESS,
                'ru6.reminder_code' => 'REM6',
                'ru7.id' => null,
            ])
            ->andWhere(['<=', 'ru6.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'utp.updated_at', 'ru6.created_at'])
            ->all();

        if (empty($usersWithRem6)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($usersWithRem6 as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM7 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM7 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM8 reminder
     * Sent 24 hours after substatus changed to FINAL_ASSIGNMENT or UNCOMPLETED_TASK. Task not completed
     */
    public function processREM8()
    {
        $twentyFourHoursAgo = time() - 86400;

        $reminderTemplate = Reminders::findOne(['code' => 'REM8']);
        if (!$reminderTemplate) {
            Yii::error("REM8 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->leftJoin('reminders_users ru', 'ru.user_id = u.id AND ru.reminder_code = \'REM8\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'ru.id' => null,
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_FINAL_ASSIGNMENT,
                User::SUBSTATUS_UNCOMPLETED_TASK,
            ]])
            ->andWhere(['<=', 'u.substatus_changed_at', $twentyFourHoursAgo])
            ->andWhere(['>', 'u.substatus_changed_at', 0])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM8 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM8 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM9 reminder
     * Sent 24 hours after REM8. Condition - final task still not completed
     */
    public function processREM9()
    {
        $twentyFourHoursAgo = time() - 86400;

        $reminderTemplate = Reminders::findOne(['code' => 'REM9']);
        if (!$reminderTemplate) {
            Yii::error("REM9 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru8', 'ru8.user_id = u.id')
            ->leftJoin('reminders_users ru9', 'ru9.user_id = u.id AND ru9.reminder_code = \'REM9\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'ru8.reminder_code' => 'REM8',
                'ru9.id' => null,
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_FINAL_ASSIGNMENT,
                User::SUBSTATUS_UNCOMPLETED_TASK,
            ]])
            ->andWhere(['<=', 'ru8.created_at', $twentyFourHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru8.created_at'])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM9 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM9 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process REM10 reminder
     * Sent 48 hours after REM9. Condition - final task still not completed
     */
    public function processREM10()
    {
        $fortyEightHoursAgo = time() - 172800;

        $reminderTemplate = Reminders::findOne(['code' => 'REM10']);
        if (!$reminderTemplate) {
            Yii::error("REM10 reminder template not found");
            return;
        }

        $users = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->innerJoin('reminders_users ru9', 'ru9.user_id = u.id')
            ->leftJoin('reminders_users ru10', 'ru10.user_id = u.id AND ru10.reminder_code = \'REM10\'')
            ->where([
                'aa.item_name' => 'employee',
                'u.status' => User::STATUS_ACTIVE,
                'ru9.reminder_code' => 'REM9',
                'ru10.id' => null,
            ])
            ->andWhere(['in', 'u.substatus', [
                User::SUBSTATUS_FINAL_ASSIGNMENT,
                User::SUBSTATUS_UNCOMPLETED_TASK,
            ]])
            ->andWhere(['<=', 'ru9.created_at', $fortyEightHoursAgo])
            ->andWhere(['<=', 'u.substatus_changed_at', 'ru9.created_at'])
            ->all();

        if (empty($users)) {
            return;
        }

        $remindersUsersService = new RemindersUsersService();

        foreach ($users as $user) {
            try {
                $remindersUsersService->createReminder($user->id, $reminderTemplate->id, true);
                Yii::info("REM10 created for user ID: {$user->id}");
            } catch (\Exception $e) {
                Yii::error("Error creating REM10 for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Create reminder for user
     *
     * @param string $reminderCode Reminder code (REM1-REM10)
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