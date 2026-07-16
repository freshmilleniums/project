<?php

use yii\db\Migration;

class m260402_151431_add_rbac_for_email_accounts extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $viewEmailAccountsList = $auth->createPermission('viewEmailAccountsList');
        $viewEmailAccountsList->description = 'View email accounts list';
        $auth->add($viewEmailAccountsList);

        $viewEmailAccount = $auth->createPermission('viewEmailAccount');
        $viewEmailAccount->description = 'View email account details';
        $auth->add($viewEmailAccount);

        $createEmailAccount = $auth->createPermission('createEmailAccount');
        $createEmailAccount->description = 'Create email account';
        $auth->add($createEmailAccount);

        $updateEmailAccount = $auth->createPermission('updateEmailAccount');
        $updateEmailAccount->description = 'Update email account';
        $auth->add($updateEmailAccount);

        $deleteEmailAccount = $auth->createPermission('deleteEmailAccount');
        $deleteEmailAccount->description = 'Delete email account';
        $auth->add($deleteEmailAccount);

        $routeEmailAccountsIndex = $auth->createPermission('/email-accounts/index');
        $routeEmailAccountsIndex->description = 'Route for viewing email accounts list';
        $auth->add($routeEmailAccountsIndex);
        $auth->addChild($viewEmailAccountsList, $routeEmailAccountsIndex);

        $routeEmailAccountsView = $auth->createPermission('/email-accounts/view');
        $routeEmailAccountsView->description = 'Route for viewing email account';
        $auth->add($routeEmailAccountsView);
        $auth->addChild($viewEmailAccount, $routeEmailAccountsView);

        $routeEmailAccountsCreate = $auth->createPermission('/email-accounts/create-ajax');
        $routeEmailAccountsCreate->description = 'Route for creating email account';
        $auth->add($routeEmailAccountsCreate);
        $auth->addChild($createEmailAccount, $routeEmailAccountsCreate);

        $routeEmailAccountsUpdate = $auth->createPermission('/email-accounts/update');
        $routeEmailAccountsUpdate->description = 'Route for updating email account';
        $auth->add($routeEmailAccountsUpdate);
        $auth->addChild($updateEmailAccount, $routeEmailAccountsUpdate);

        $routeEmailAccountsDelete = $auth->createPermission('/email-accounts/delete');
        $routeEmailAccountsDelete->description = 'Route for deleting email account';
        $auth->add($routeEmailAccountsDelete);
        $auth->addChild($deleteEmailAccount, $routeEmailAccountsDelete);

        $superAdmin = $auth->getRole('super-administrator');
        $admin = $auth->getRole('administrator');

        $permissions = [
            $viewEmailAccountsList,
            $viewEmailAccount,
            $createEmailAccount,
            $updateEmailAccount,
            $deleteEmailAccount,
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

        $routes = [
            '/email-accounts/index',
            '/email-accounts/view',
            '/email-accounts/create-ajax',
            '/email-accounts/update',
            '/email-accounts/delete',
        ];

        foreach ($routes as $route) {
            $routePermission = $auth->getPermission($route);
            if ($routePermission) {
                $auth->remove($routePermission);
            }
        }

        $permissions = [
            'viewEmailAccountsList',
            'viewEmailAccount',
            'createEmailAccount',
            'updateEmailAccount',
            'deleteEmailAccount',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
