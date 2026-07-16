<?php

use yii\db\Migration;

class m260612_005045_add_rbac_for_email_accounts_extra_routes extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // These permissions already exist from m260402_151431_add_rbac_for_email_accounts
        $updateEmailAccount = $auth->getPermission('updateEmailAccount');
        $createEmailAccount = $auth->getPermission('createEmailAccount');

        // ============================================
        // Routes
        // ============================================

        if (!$auth->getPermission('/email-accounts/test-connection')) {
            $routeTestConnection = $auth->createPermission('/email-accounts/test-connection');
            $routeTestConnection->description = 'Route for testing email account connection';
            $auth->add($routeTestConnection);
            $auth->addChild($updateEmailAccount, $routeTestConnection);
        }

        if (!$auth->getPermission('/email-accounts/assign-admins')) {
            $routeAssignAdmins = $auth->createPermission('/email-accounts/assign-admins');
            $routeAssignAdmins->description = 'Route for viewing assign admins form';
            $auth->add($routeAssignAdmins);
            $auth->addChild($updateEmailAccount, $routeAssignAdmins);
        }

        if (!$auth->getPermission('/email-accounts/save-admins')) {
            $routeSaveAdmins = $auth->createPermission('/email-accounts/save-admins');
            $routeSaveAdmins->description = 'Route for saving assigned admins';
            $auth->add($routeSaveAdmins);
            $auth->addChild($updateEmailAccount, $routeSaveAdmins);
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $routes = [
            '/email-accounts/test-connection',
            '/email-accounts/assign-admins',
            '/email-accounts/save-admins',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
