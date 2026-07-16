<?php

namespace common\services;

use backend\models\NotificationModel;
use common\models\User;
use yii\web\NotFoundHttpException;
use Yii;

/**
 * Service for handling notification business logic
 */
class NotificationService
{
    /**
     * Get unread notifications for user with pagination and filtering
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getUnreadNotificationsData($userId, $limit = null, $offset = 0)
    {
        $query = NotificationModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->orderBy(['created_at' => SORT_DESC]);

        $totalCount = $query->count();

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $notifications = $query->all();

        return [
            'notifications' => $notifications,
            'totalCount' => $totalCount,
            'hasMore' => $totalCount > ($offset + count($notifications))
        ];
    }

    /**
     * Get unread notifications for user (simple version for display)
     * @param int $userId
     * @return NotificationModel[]
     */
    public function getUnreadNotifications($userId)
    {
        return NotificationModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }

    /**
     * Get unread notifications count for user
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId)
    {
        return NotificationModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->count();
    }

    /**
     * Find notification by ID and user ID
     * @param int $id
     * @param int $userId
     * @return NotificationModel
     * @throws NotFoundHttpException
     */
    public function findNotificationForUser($id, $userId)
    {
        $notification = NotificationModel::findOne(['id' => $id, 'user_id' => $userId]);

        if (!$notification) {
            throw new NotFoundHttpException('Notification not found.');
        }

        return $notification;
    }

    /**
     * Mark single notification as read
     * @param int $id
     * @param int $userId
     * @return NotificationModel
     * @throws NotFoundHttpException
     */
    public function markNotificationAsRead($id, $userId)
    {
        $notification = $this->findNotificationForUser($id, $userId);

        // Only update if not already read
        if ($notification->isUnread()) {
            $notification->read = 1;
            $notification->save(false);
        }

        return $notification;
    }

    /**
     * Mark all unread notifications as read for user
     * @param int $userId
     * @return bool
     * @throws NotFoundHttpException
     */
    public function markAllNotificationsAsRead($userId)
    {
        // Check if user has any unread notifications
        $hasUnread = NotificationModel::find()
            ->where(['user_id' => $userId, 'read' => 0])
            ->exists();

        if (!$hasUnread) {
            throw new NotFoundHttpException('No unread notifications found.');
        }

        // Mark all as read
        NotificationModel::updateAll(
            ['read' => 1],
            ['user_id' => $userId, 'read' => 0]
        );

        return true;
    }

    /**
     * Resend notification by email
     * @param int $id
     * @param int $userId
     * @param int $resentBy
     * @return NotificationModel
     * @throws NotFoundHttpException
     */
    public function resentNotification($id, $userId, $resentBy)
    {
        $notification = $this->findNotificationForUser($id, $userId);

        // Update resent information
        $notification->resent_at = time();
        $notification->resent_by = $resentBy;
        $notification->read = 0;
        $notification->save(false);

        // Send email
        $this->sendNotificationEmail($notification);

        return $notification;
    }

    /**
     * Create new notification
     * @param int $userId
     * @param string $text
     * @param bool $sendEmail
     * @return NotificationModel
     */
    public function createNotification($userId, $text, $sendEmail = true)
    {
        $notification = new NotificationModel();
        $notification->user_id = $userId;
        $notification->text = $text;
        $notification->created_at = time();
        $notification->save();

        if ($sendEmail) {
            $this->sendNotificationEmail($notification);
        }

        return $notification;
    }

    /**
     * Send notification via email
     * @param NotificationModel $notification
     * @return bool
     */
    public function sendNotificationEmail(NotificationModel $notification)
    {
        try {
            $user = $notification->user;

            if (!$user || !$user->email) {
                Yii::error('User or email not found for notification ID: ' . $notification->id);
                return false;
            }

            $subject = 'New Notification';
            $body = $this->prepareEmailBody($notification);

            return Yii::$app->mailer
                ->compose()
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setTo($user->email)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Exception $e) {
            Yii::error('Error sending notification email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare email body for notification
     * @param NotificationModel $notification
     * @return string
     */
    private function prepareEmailBody(NotificationModel $notification)
    {
        $body = "Hello,\n\n";
        $body .= $notification->text . "\n\n";

        $body .= "Best regards,\n";
        $body .= Yii::$app->name;

        return $body;
    }

    /**
     * Get all notifications for user with pagination
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllNotificationsData($userId, $limit = null, $offset = 0)
    {
        $query = NotificationModel::find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => SORT_DESC]);

        $totalCount = $query->count();

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $notifications = $query->all();

        return [
            'notifications' => $notifications,
            'totalCount' => $totalCount,
            'hasMore' => $totalCount > ($offset + count($notifications))
        ];
    }

    /**
     * Send welcome notification to newly registered user
     * @param int $userId
     * @return NotificationModel
     */
    public function sendWelcomeNotification($userId)
    {
        $text = "Welcome to our courier platform! Thank you for registering with us. " .
            "Your application has been received and will be reviewed by our HR team. " .
            "You will receive further instructions about the next steps in the recruitment process soon.";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send test completion notification after HR review
     * @param int $userId
     * @param string $interviewDate
     * @param string $interviewTime
     * @param string $contactInfo
     * @return NotificationModel
     */
    public function sendTestCompletionNotification($userId, $interviewDate = '', $interviewTime = '', $contactInfo = '')
    {
        $text = "Congratulations! Your test results have been reviewed and approved by our HR team. 
            The next step is a phone interview.
            Please be available at the scheduled time. Good luck!";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send contract available notification
     * @param int $userId
     * @return NotificationModel
     */
    public function sendContractAvailableNotification($userId)
    {
        $text = "Great news! Your contract is now available in your personal dashboard. " .
            "Please log in to your account to review the contract terms and conditions. " .
            "Once you have carefully read through the document, please sign and upload it back to the system. " .
            "If you have any questions about the contract, please contact our HR department.";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send contract signed confirmation notification
     * @param int $userId
     * @return NotificationModel
     */
    public function sendContractSignedNotification($userId)
    {
        $text = "Congratulations! You are now officially part of our courier team! " .
            "Your signed contract has been processed and approved. " .
            "You will soon receive your first delivery assignment. " .
            "Please make sure to check your dashboard regularly for new tasks and updates. " .
            "Welcome aboard!";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send first package notification
     * @param int $userId
     * @param string $trackingNumber
     * @param string $estimatedDelivery
     * @return NotificationModel
     */
    public function sendFirstPackageNotification($userId)
    {
        $text = "Your first package has been dispatched and is on its way to you!
            This package contains all the necessary materials and instructions for your first delivery package. " .
            "Please confirm receipt in your dashboard once you receive it.";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send new task notification
     * @param int $userId
     * @param string $taskId
     * @param string $taskType
     * @return NotificationModel
     */
    public function sendFirstTaskNotification($userId)
    {
        $text = "Your first task has been dispatched and is on its way to you!
            This task contains all the necessary materials and instructions for your first delivery task. " .
            "Please confirm receipt in your dashboard once you receive it.";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send task completed notification
     * @param int $userId
     * @param string $taskId
     * @param string $completionTime
     * @param float $earnings
     * @return NotificationModel
     */
    public function sendTaskCompletedNotification($userId)
    {
        $text = "Excellent work! Your task has been successfully completed and verified. 
            Thank you for your professional service. Keep up the great work!";

        return $this->createNotification($userId, $text, true);
    }

    /**
     * Send package completed notification
     * @param int $userId
     * @param string $completionTime
     * @param float $earnings
     * @return NotificationModel
     */
    public function sendPackageCompletedNotification($userId)
    {
        $text = "Excellent work! Your package has been successfully completed and verified. 
            Thank you for your professional service. Keep up the great work!";

        return $this->createNotification($userId, $text, true);
    }
}