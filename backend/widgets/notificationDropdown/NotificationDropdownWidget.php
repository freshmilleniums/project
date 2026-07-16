<?php

namespace backend\widgets\notificationDropdown;

use yii\base\Widget;
use common\services\NotificationService;
use common\services\RemindersUsersService;
use Yii;

class NotificationDropdownWidget extends Widget
{
    public function run()
    {
        $userId = Yii::$app->user->id;

        $notificationService = new NotificationService();
        $reminderService = new RemindersUsersService();

        $notificationCount = $notificationService->getUnreadCount($userId);
        $reminderCount = $reminderService->getUnreadCount($userId);

        return $this->render('notification-dropdown', [
            'notificationCount' => $notificationCount,
            'reminderCount' => $reminderCount,
        ]);
    }
}
