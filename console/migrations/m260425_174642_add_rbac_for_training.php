<?php

use yii\db\Migration;

class m260425_174642_add_rbac_for_training extends Migration
{
    public function up()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Training Management (Administrators)
        // ============================================

        $manageTrainingModules = $auth->createPermission('manageTrainingModules');
        $manageTrainingModules->description = 'Manage training modules and questions';
        $auth->add($manageTrainingModules);

        $routes = [
            '/training/index',
            '/training/create-module',
            '/training/update-module',
            '/training/delete-module',
            '/training/update-sort',
            '/training/questions',
            '/training/create-question',
            '/training/update-question',
            '/training/delete-question',
            '/training/update-question-sort',
        ];

        foreach ($routes as $route) {
            $permission = $auth->createPermission($route);
            $permission->description = 'Route: ' . $route;
            $auth->add($permission);
            $auth->addChild($manageTrainingModules, $permission);
        }

        $superAdmin = $auth->getRole('super-administrator');
        if ($superAdmin) {
            $auth->addChild($superAdmin, $manageTrainingModules);
        }

        $admin = $auth->getRole('administrator');
        if ($admin) {
            $auth->addChild($admin, $manageTrainingModules);
        }

        // ============================================
        //  Training Access Employees
        // ============================================

        $accessTraining = $auth->createPermission('accessTraining');
        $accessTraining->description = 'Access training modules (employee side)';
        $auth->add($accessTraining);

        // Routes for employee training
        $employeeRoutes = [
            '/personal/training',
            '/personal/module',
            '/personal/module-test',
            '/personal/submit-module-test',
        ];

        foreach ($employeeRoutes as $route) {
            $permission = $auth->createPermission($route);
            $permission->description = 'Route: ' . $route;
            $auth->add($permission);
            $auth->addChild($accessTraining, $permission);
        }

        // Assign to employee role
        $employee = $auth->getRole('employee');
        if ($employee) {
            $auth->addChild($employee, $accessTraining);
        }
    }

    public function down()
    {
        $auth = Yii::$app->authManager;

        $superAdmin = $auth->getRole('super-administrator');
        if ($superAdmin) {
            $manageTrainingModules = $auth->getPermission('manageTrainingModules');
            if ($manageTrainingModules) {
                $auth->removeChild($superAdmin, $manageTrainingModules);
            }
        }

        $admin = $auth->getRole('administrator');
        if ($admin) {
            $manageTrainingModules = $auth->getPermission('manageTrainingModules');
            if ($manageTrainingModules) {
                $auth->removeChild($admin, $manageTrainingModules);
            }
        }

        $employee = $auth->getRole('employee');
        if ($employee) {
            $accessTraining = $auth->getPermission('accessTraining');
            if ($accessTraining) {
                $auth->removeChild($employee, $accessTraining);
            }
        }

        $routes = [
            '/training/index',
            '/training/create-module',
            '/training/update-module',
            '/training/delete-module',
            '/training/update-sort',
            '/training/questions',
            '/training/create-question',
            '/training/update-question',
            '/training/delete-question',
            '/training/update-question-sort',
        ];

        foreach ($routes as $route) {
            $auth->remove($auth->getPermission($route));
        }

        $employeeRoutes = [
            '/personal/training',
            '/personal/module',
            '/personal/module-test',
            '/personal/submit-module-test',
        ];

        foreach ($employeeRoutes as $route) {
            $auth->remove($auth->getPermission($route));
        }

        $auth->remove($auth->getPermission('manageTrainingModules'));
        $auth->remove($auth->getPermission('accessTraining'));
    }
}
