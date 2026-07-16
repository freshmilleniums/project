<?php

use yii\db\Migration;

class m250518_003313_add_user_permissions extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $createUser = $auth->createPermission('createUser');
        $createUser->description = 'Create user';
        $auth->add($createUser);

        $updateUser = $auth->createPermission('updateUser');
        $updateUser->description = 'Update user';
        $auth->add($updateUser);

        $deleteUser = $auth->createPermission('deleteUser');
        $deleteUser->description = 'Delete user';
        $auth->add($deleteUser);

        $viewUser = $auth->createPermission('viewUser');
        $viewUser->description = 'View user details';
        $auth->add($viewUser);

        $viewUserList = $auth->createPermission('viewUserList');
        $viewUserList->description = 'View users list';
        $auth->add($viewUserList);


        $routeCreate = $auth->createPermission('/users/create');
        $routeCreate->description = 'Route for user creation';
        $auth->add($routeCreate);

        $routeUpdate = $auth->createPermission('/users/update');
        $routeUpdate->description = 'Route for user update';
        $auth->add($routeUpdate);

        $routeDelete = $auth->createPermission('/users/delete');
        $routeDelete->description = 'Route for user deletion';
        $auth->add($routeDelete);

        $routeView = $auth->createPermission('/users/view');
        $routeView->description = 'Route for viewing user details';
        $auth->add($routeView);

        $routeIndex = $auth->createPermission('/users/index');
        $routeIndex->description = 'Route for viewing users list';
        $auth->add($routeIndex);


        $auth->addChild($createUser, $routeCreate);
        $auth->addChild($updateUser, $routeUpdate);
        $auth->addChild($deleteUser, $routeDelete);
        $auth->addChild($viewUser, $routeView);
        $auth->addChild($viewUserList, $routeIndex);


        $adminRoles = ['super-administrator', 'administrator'];

        foreach ($adminRoles as $roleName) {
            $role = $auth->getRole($roleName);
            if ($role) {
                $auth->addChild($role, $createUser);
                $auth->addChild($role, $updateUser);
                $auth->addChild($role, $deleteUser);
                $auth->addChild($role, $viewUser);
                $auth->addChild($role, $viewUserList);
            }
        }

        $phoneOperator = $auth->getRole('phone-operator');
        if ($phoneOperator) {
            $auth->addChild($phoneOperator, $viewUser);
            $auth->addChild($phoneOperator, $viewUserList);
        }

        $emailTaskOperator = $auth->getRole('email-task-operator');
        if ($emailTaskOperator) {
            $auth->addChild($emailTaskOperator, $viewUser);
            $auth->addChild($emailTaskOperator, $viewUserList);
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $permissions = [
            'createUser',
            'updateUser',
            'deleteUser',
            'viewUser',
            'viewUserList',
            '/users/create',
            '/users/update',
            '/users/delete',
            '/users/view',
            '/users/index',
        ];

        foreach ($permissions as $permission) {
            if ($perm = $auth->getPermission($permission)) {
                $auth->remove($perm);
            }
        }
    }
}