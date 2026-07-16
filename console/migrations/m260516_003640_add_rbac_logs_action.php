<?php

use yii\db\Migration;

class m260516_003640_add_rbac_logs_action extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $viewLogs = $auth->createPermission('viewLogsAction');
        $viewLogs->description = 'View action logs';
        $auth->add($viewLogs);

        $viewLogDetails = $auth->createPermission('viewLogDetails');
        $viewLogDetails->description = 'View action log details';
        $auth->add($viewLogDetails);

        // Create routes
        $routeLogsIndex = $auth->createPermission('/logs-action/index');
        $routeLogsIndex->description = 'Route for viewing action logs list';
        $auth->add($routeLogsIndex);
        $auth->addChild($viewLogs, $routeLogsIndex);

        $routeLogsView = $auth->createPermission('/logs-action/view');
        $routeLogsView->description = 'Route for viewing action log details';
        $auth->add($routeLogsView);
        $auth->addChild($viewLogDetails, $routeLogsView);

        $superAdmin = $auth->getRole('super-administrator');
        $admin = $auth->getRole('administrator');

        $permissions = [
            $viewLogs,
            $viewLogDetails,
        ];

        if ($superAdmin) {
            foreach ($permissions as $permission) {
                if ($permission && !$auth->hasChild($superAdmin, $permission)) {
                    $auth->addChild($superAdmin, $permission);
                }
            }
        }

        if ($admin) {
            foreach ($permissions as $permission) {
                if ($permission && !$auth->hasChild($admin, $permission)) {
                    $auth->addChild($admin, $permission);
                }
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        // Remove routes
        $routes = [
            '/logs-action/index',
            '/logs-action/view',
        ];

        foreach ($routes as $route) {
            $routePermission = $auth->getPermission($route);
            if ($routePermission) {
                $auth->remove($routePermission);
            }
        }

        $permissions = [
            'viewLogsAction',
            'viewLogDetails',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
