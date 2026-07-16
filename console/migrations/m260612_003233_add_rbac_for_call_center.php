<?php

use yii\db\Migration;

class m260612_003233_add_rbac_for_call_center extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions
        // ============================================

        $viewCallCenter = $auth->createPermission('viewCallCenter');
        $viewCallCenter->description = 'Access call center';
        $auth->add($viewCallCenter);

        $manageScheduledCalls = $auth->createPermission('manageScheduledCalls');
        $manageScheduledCalls->description = 'Schedule, complete and delete scheduled calls';
        $auth->add($manageScheduledCalls);

        // ============================================
        // Routes
        // ============================================

        $routeCallCenterIndex = $auth->createPermission('/call-center/index');
        $routeCallCenterIndex->description = 'Route for viewing call center';
        $auth->add($routeCallCenterIndex);
        $auth->addChild($viewCallCenter, $routeCallCenterIndex);

        $routeCallCenterScheduleCall = $auth->createPermission('/call-center/schedule-call');
        $routeCallCenterScheduleCall->description = 'Route for scheduling a call';
        $auth->add($routeCallCenterScheduleCall);
        $auth->addChild($manageScheduledCalls, $routeCallCenterScheduleCall);

        $routeCallCenterMarkCallDone = $auth->createPermission('/call-center/mark-call-done');
        $routeCallCenterMarkCallDone->description = 'Route for marking call as done';
        $auth->add($routeCallCenterMarkCallDone);
        $auth->addChild($manageScheduledCalls, $routeCallCenterMarkCallDone);

        $routeCallCenterDeleteScheduledCall = $auth->createPermission('/call-center/delete-scheduled-call');
        $routeCallCenterDeleteScheduledCall->description = 'Route for deleting scheduled call';
        $auth->add($routeCallCenterDeleteScheduledCall);
        $auth->addChild($manageScheduledCalls, $routeCallCenterDeleteScheduledCall);

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin    = $auth->getRole('super-administrator');
        $admin         = $auth->getRole('administrator');
        $phoneOperator = $auth->getRole('phone-operator');

        $permissions = [
            $viewCallCenter,
            $manageScheduledCalls,
        ];

        foreach ([$superAdmin, $admin, $phoneOperator] as $role) {
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
            '/call-center/index',
            '/call-center/schedule-call',
            '/call-center/mark-call-done',
            '/call-center/delete-scheduled-call',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permissions = [
            'viewCallCenter',
            'manageScheduledCalls',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
