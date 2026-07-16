<?php

use yii\db\Migration;

class m260612_003300_add_rbac_for_reminders extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions
        // ============================================

        $viewRemindersList = $auth->createPermission('viewRemindersList');
        $viewRemindersList->description = 'View reminders list';
        $auth->add($viewRemindersList);

        $updateReminder = $auth->createPermission('updateReminder');
        $updateReminder->description = 'Update reminder';
        $auth->add($updateReminder);

        // ============================================
        // Routes
        // ============================================

        $routeRemindersIndex = $auth->createPermission('/reminders/index');
        $routeRemindersIndex->description = 'Route for viewing reminders list';
        $auth->add($routeRemindersIndex);
        $auth->addChild($viewRemindersList, $routeRemindersIndex);

        $routeRemindersUpdate = $auth->createPermission('/reminders/update');
        $routeRemindersUpdate->description = 'Route for updating reminder';
        $auth->add($routeRemindersUpdate);
        $auth->addChild($updateReminder, $routeRemindersUpdate);

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin = $auth->getRole('super-administrator');
        $admin      = $auth->getRole('administrator');

        $permissions = [
            $viewRemindersList,
            $updateReminder,
        ];

        foreach ([$superAdmin, $admin] as $role) {
            if ($role) {
                foreach ($permissions as $permission) {
                    if ($permission && !$auth->hasChild($role, $permission)) {
                        $auth->addChild($role, $permission);
                    }
                }
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $routes = [
            '/reminders/index',
            '/reminders/update',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permissions = [
            'viewRemindersList',
            'updateReminder',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
