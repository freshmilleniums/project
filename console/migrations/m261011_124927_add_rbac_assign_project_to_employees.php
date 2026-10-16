<?php

use yii\db\Migration;

class m261011_124927_add_rbac_assign_project_to_employees extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        if (!$auth->getPermission('/projects/assign-to-employees')) {
            $route = $auth->createPermission('/projects/assign-to-employees');
            $route->description = 'Route for bulk assigning project to employees';
            $auth->add($route);

            $assignEmployeeToProject = $auth->getPermission('assignEmployeeToProject');
            if ($assignEmployeeToProject) {
                $auth->addChild($assignEmployeeToProject, $route);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $permission = $auth->getPermission('/projects/assign-to-employees');
        if ($permission) {
            $auth->remove($permission);
        }
    }
}
