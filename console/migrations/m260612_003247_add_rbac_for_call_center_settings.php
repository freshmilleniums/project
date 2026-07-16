<?php

use yii\db\Migration;

class m260612_003247_add_rbac_for_call_center_settings extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions
        // ============================================

        $viewCallCenterSettings = $auth->createPermission('viewCallCenterSettings');
        $viewCallCenterSettings->description = 'View call center settings';
        $auth->add($viewCallCenterSettings);

        $manageCallCenterDistribution = $auth->createPermission('manageCallCenterDistribution');
        $manageCallCenterDistribution->description = 'Manage call center operator distribution';
        $auth->add($manageCallCenterDistribution);

        $manageCallCenterScripts = $auth->createPermission('manageCallCenterScripts');
        $manageCallCenterScripts->description = 'Manage call center scripts';
        $auth->add($manageCallCenterScripts);

        // ============================================
        // Routes
        // ============================================

        $routeCallCenterSettingsIndex = $auth->createPermission('/call-center-settings/index');
        $routeCallCenterSettingsIndex->description = 'Route for viewing call center settings';
        $auth->add($routeCallCenterSettingsIndex);
        $auth->addChild($viewCallCenterSettings, $routeCallCenterSettingsIndex);

        $routeCallCenterSettingsSaveDistribution = $auth->createPermission('/call-center-settings/save-distribution');
        $routeCallCenterSettingsSaveDistribution->description = 'Route for saving operator distribution';
        $auth->add($routeCallCenterSettingsSaveDistribution);
        $auth->addChild($manageCallCenterDistribution, $routeCallCenterSettingsSaveDistribution);

        $routeCallCenterSettingsCreateScript = $auth->createPermission('/call-center-settings/create-script');
        $routeCallCenterSettingsCreateScript->description = 'Route for creating call center script';
        $auth->add($routeCallCenterSettingsCreateScript);
        $auth->addChild($manageCallCenterScripts, $routeCallCenterSettingsCreateScript);

        $routeCallCenterSettingsUpdateScript = $auth->createPermission('/call-center-settings/update-script');
        $routeCallCenterSettingsUpdateScript->description = 'Route for updating call center script';
        $auth->add($routeCallCenterSettingsUpdateScript);
        $auth->addChild($manageCallCenterScripts, $routeCallCenterSettingsUpdateScript);

        $routeCallCenterSettingsViewScript = $auth->createPermission('/call-center-settings/view-script');
        $routeCallCenterSettingsViewScript->description = 'Route for viewing call center script';
        $auth->add($routeCallCenterSettingsViewScript);
        $auth->addChild($manageCallCenterScripts, $routeCallCenterSettingsViewScript);

        $routeCallCenterSettingsDeleteScript = $auth->createPermission('/call-center-settings/delete-script');
        $routeCallCenterSettingsDeleteScript->description = 'Route for deleting call center script';
        $auth->add($routeCallCenterSettingsDeleteScript);
        $auth->addChild($manageCallCenterScripts, $routeCallCenterSettingsDeleteScript);

        $routeCallCenterSettingsUpdateSort = $auth->createPermission('/call-center-settings/update-sort');
        $routeCallCenterSettingsUpdateSort->description = 'Route for updating scripts sort order';
        $auth->add($routeCallCenterSettingsUpdateSort);
        $auth->addChild($manageCallCenterScripts, $routeCallCenterSettingsUpdateSort);

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin = $auth->getRole('super-administrator');
        $admin      = $auth->getRole('administrator');

        $permissions = [
            $viewCallCenterSettings,
            $manageCallCenterDistribution,
            $manageCallCenterScripts,
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
            '/call-center-settings/index',
            '/call-center-settings/save-distribution',
            '/call-center-settings/create-script',
            '/call-center-settings/update-script',
            '/call-center-settings/view-script',
            '/call-center-settings/delete-script',
            '/call-center-settings/update-sort',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permissions = [
            'viewCallCenterSettings',
            'manageCallCenterDistribution',
            'manageCallCenterScripts',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
