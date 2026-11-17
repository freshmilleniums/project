<?php

use yii\db\Migration;

class m261113_021210_add_rbac_for_users_inline_change_status extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $changeUserStatus = $auth->getPermission('changeUserStatus');

        if (!$auth->getPermission('/users/inline-change-status')) {
            $routeUsersInlineChangeStatus = $auth->createPermission('/users/inline-change-status');
            $routeUsersInlineChangeStatus->description = 'Route for inline changing employee status from users list';
            $auth->add($routeUsersInlineChangeStatus);
            if ($changeUserStatus) {
                $auth->addChild($changeUserStatus, $routeUsersInlineChangeStatus);
            }
        }

        $emailTaskOperator = $auth->getRole('email-task-operator');

        $emailTaskOperatorPermissions = [
            'changeUserStatus',
            'viewCallCenter',
            'viewCallCenterCard',
            'manageScheduledCalls',
        ];

        if ($emailTaskOperator) {
            foreach ($emailTaskOperatorPermissions as $permissionName) {
                $permission = $auth->getPermission($permissionName);
                if ($permission && !$auth->hasChild($emailTaskOperator, $permission)) {
                    $auth->addChild($emailTaskOperator, $permission);
                }
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $emailTaskOperator = $auth->getRole('email-task-operator');

        $emailTaskOperatorPermissions = [
            'changeUserStatus',
            'viewCallCenter',
            'viewCallCenterCard',
            'manageScheduledCalls',
        ];

        if ($emailTaskOperator) {
            foreach ($emailTaskOperatorPermissions as $permissionName) {
                $permission = $auth->getPermission($permissionName);
                if ($permission && $auth->hasChild($emailTaskOperator, $permission)) {
                    $auth->removeChild($emailTaskOperator, $permission);
                }
            }
        }

        $permission = $auth->getPermission('/users/inline-change-status');
        if ($permission) {
            $auth->remove($permission);
        }
    }
}
