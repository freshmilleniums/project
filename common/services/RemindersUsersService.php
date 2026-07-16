<?php

namespace common\services;

use backend\models\RemindersUsersModel;
use common\models\Reminders;
use yii\web\NotFoundHttpException;
use Yii;

/**
 * Service for handling reminders_users business logic
 */
class RemindersUsersService
{
    /**
     * Get unread reminders for user with pagination and filtering
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getUnreadRemindersData($userId, $limit = null, $offset = 0)
    {
        $query = RemindersUsersModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->orderBy(['created_at' => SORT_DESC]);

        $totalCount = $query->count();

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $reminders = $query->all();

        return [
            'reminders' => $reminders,
            'totalCount' => $totalCount,
            'hasMore' => $totalCount > ($offset + count($reminders))
        ];
    }

    /**
     * Get unread reminders for user (simple version for display)
     * @param int $userId
     * @return RemindersUsersModel[]
     */
    public function getUnreadReminders($userId)
    {
        return RemindersUsersModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }

    /**
     * Get unread reminders count for user
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId)
    {
        return RemindersUsersModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->count();
    }

    /**
     * Find reminder by ID and user ID
     * @param int $id
     * @param int $userId
     * @return RemindersUsersModel
     * @throws NotFoundHttpException
     */
    public function findReminderForUser($id, $userId)
    {
        $reminder = RemindersUsersModel::findOne(['id' => $id, 'user_id' => $userId]);

        if (!$reminder) {
            throw new NotFoundHttpException('Reminder not found.');
        }

        return $reminder;
    }

    /**
     * Mark single reminder as read
     * @param int $id
     * @param int $userId
     * @return RemindersUsersModel
     * @throws NotFoundHttpException
     */
    public function markReminderAsRead($id, $userId)
    {
        $reminder = $this->findReminderForUser($id, $userId);

        // Only update if not already read
        if ($reminder->isUnread()) {
            $reminder->read = 1;
            $reminder->save(false);
        }

        return $reminder;
    }

    /**
     * Mark all unread reminders as read for user
     * @param int $userId
     * @return bool
     * @throws NotFoundHttpException
     */
    public function markAllRemindersAsRead($userId)
    {
        // Check if user has any unread reminders
        $hasUnread = RemindersUsersModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->exists();

        if (!$hasUnread) {
            throw new NotFoundHttpException('No unread reminders found.');
        }

        // Mark all as read
        RemindersUsersModel::updateAll(
            ['read' => 1],
            ['user_id' => $userId, 'read' => 0]
        );

        return true;
    }

    /**
     * Create new reminder from template
     * @param int $userId
     * @param int $reminderId
     * @param bool $sendEmail
     * @return RemindersUsersModel
     * @throws NotFoundHttpException
     */
    public function createReminder($userId, $reminderId, $sendEmail = true)
    {
        // Find reminder template
        $reminderTemplate = Reminders::findOne($reminderId);

        if (!$reminderTemplate) {
            throw new NotFoundHttpException('Reminder template not found for ID: ' . $reminderId);
        }

        // Create new reminder for user
        $reminderUser = new RemindersUsersModel();
        $reminderUser->reminder_id = $reminderTemplate->id;
        $reminderUser->reminder_code = $reminderTemplate->code;
        $reminderUser->user_id = $userId;
        $reminderUser->text = $reminderTemplate->text;
        $reminderUser->created_at = time();
        $reminderUser->read = 0;
        $reminderUser->save();

        if ($sendEmail) {
            $this->sendReminderEmail($reminderUser);
        }

        return $reminderUser;
    }

    /**
     * Send reminder via email
     * @param RemindersUsersModel $reminder
     * @return bool
     */
    public function sendReminderEmail(RemindersUsersModel $reminder)
    {
        try {
            $user = $reminder->user;

            if (!$user || !$user->email) {
                Yii::error('User or email not found for reminder ID: ' . $reminder->id);
                return false;
            }

            $subject = 'Reminder ';
            $body = $this->prepareEmailBody($reminder);

            return Yii::$app->mailer
                ->compose()
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setTo($user->email)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Exception $e) {
            Yii::error('Error sending reminder email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare email body for reminder
     * @param RemindersUsersModel $reminder
     * @return string
     */
    private function prepareEmailBody(RemindersUsersModel $reminder)
    {
        $body = "Hello,\n\n";

        if ($reminder->text) {
            $body .= $reminder->text . "\n\n";
        }

        $body .= "Best regards,\n";
        $body .= Yii::$app->name;

        return $body;
    }

    /**
     * Get all reminders for user with pagination
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllRemindersData($userId, $limit = null, $offset = 0)
    {
        $query = RemindersUsersModel::find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => SORT_DESC]);

        $totalCount = $query->count();

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $reminders = $query->all();

        return [
            'reminders' => $reminders,
            'totalCount' => $totalCount,
            'hasMore' => $totalCount > ($offset + count($reminders))
        ];
    }


}