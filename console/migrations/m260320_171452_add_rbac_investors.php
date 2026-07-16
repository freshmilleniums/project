<?php

use yii\db\Migration;

class m260320_171452_add_rbac_investors extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // Create permissions
        $viewInvestorsList = $auth->createPermission('viewInvestorsList');
        $viewInvestorsList->description = 'View investors list';
        $auth->add($viewInvestorsList);

        $viewInvestor = $auth->createPermission('viewInvestor');
        $viewInvestor->description = 'View investor details';
        $auth->add($viewInvestor);

        $createInvestor = $auth->createPermission('createInvestor');
        $createInvestor->description = 'Create new investor';
        $auth->add($createInvestor);

        $updateInvestor = $auth->createPermission('updateInvestor');
        $updateInvestor->description = 'Update investor details';
        $auth->add($updateInvestor);

        $deleteInvestor = $auth->createPermission('deleteInvestor');
        $deleteInvestor->description = 'Delete investor';
        $auth->add($deleteInvestor);

        $assignInvestorToEmployee = $auth->createPermission('assignInvestorToEmployee');
        $assignInvestorToEmployee->description = 'Assign investors to employees';
        $auth->add($assignInvestorToEmployee);

        // Create routes
        $routeInvestorsIndex = $auth->createPermission('/investors/index');
        $routeInvestorsIndex->description = 'Route for viewing investors list';
        $auth->add($routeInvestorsIndex);
        $auth->addChild($viewInvestorsList, $routeInvestorsIndex);

        $routeInvestorsView = $auth->createPermission('/investors/view');
        $routeInvestorsView->description = 'Route for viewing investor details';
        $auth->add($routeInvestorsView);
        $auth->addChild($viewInvestor, $routeInvestorsView);

        $routeInvestorsCreate = $auth->createPermission('/investors/create-ajax');
        $routeInvestorsCreate->description = 'Route for creating investor';
        $auth->add($routeInvestorsCreate);
        $auth->addChild($createInvestor, $routeInvestorsCreate);

        $routeInvestorsUpdate = $auth->createPermission('/investors/update');
        $routeInvestorsUpdate->description = 'Route for updating investor';
        $auth->add($routeInvestorsUpdate);
        $auth->addChild($updateInvestor, $routeInvestorsUpdate);

        $routeInvestorsDelete = $auth->createPermission('/investors/delete');
        $routeInvestorsDelete->description = 'Route for deleting investor';
        $auth->add($routeInvestorsDelete);
        $auth->addChild($deleteInvestor, $routeInvestorsDelete);

        $routeAssignEmployees = $auth->createPermission('/investors/assign-employees');
        $routeAssignEmployees->description = 'Route for assigning employees to investor';
        $auth->add($routeAssignEmployees);
        $auth->addChild($assignInvestorToEmployee, $routeAssignEmployees);

        $routeBulkAssign = $auth->createPermission('/investors/bulk-assign-employee');
        $routeBulkAssign->description = 'Route for bulk assigning investors to employee';
        $auth->add($routeBulkAssign);
        $auth->addChild($assignInvestorToEmployee, $routeBulkAssign);

        $routeGetEmployees = $auth->createPermission('/investors/get-employees');
        $routeGetEmployees->description = 'Route for getting employees list';
        $auth->add($routeGetEmployees);
        $auth->addChild($assignInvestorToEmployee, $routeGetEmployees);

        // Assign permissions to roles
        $superAdmin = $auth->getRole('super-administrator');
        $admin = $auth->getRole('administrator');

        $permissions = [
            $viewInvestorsList,
            $viewInvestor,
            $createInvestor,
            $updateInvestor,
            $deleteInvestor,
            $assignInvestorToEmployee,
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
            '/investors/index',
            '/investors/view',
            '/investors/create-ajax',
            '/investors/update',
            '/investors/delete',
            '/investors/assign-employees',
            '/investors/bulk-assign-employee',
            '/investors/get-employees',
        ];

        foreach ($routes as $route) {
            $routePermission = $auth->getPermission($route);
            if ($routePermission) {
                $auth->remove($routePermission);
            }
        }

        $permissions = [
            'viewInvestorsList',
            'viewInvestor',
            'createInvestor',
            'updateInvestor',
            'deleteInvestor',
            'assignInvestorToEmployee',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
