<?php

use yii\db\Migration;

class m260612_001127_add_rbac_for_projects extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions
        // ============================================

        $viewProjectsList = $auth->createPermission('viewProjectsList');
        $viewProjectsList->description = 'View projects list';
        $auth->add($viewProjectsList);

        $viewProject = $auth->createPermission('viewProject');
        $viewProject->description = 'View project details';
        $auth->add($viewProject);

        $createProject = $auth->createPermission('createProject');
        $createProject->description = 'Create project';
        $auth->add($createProject);

        $updateProject = $auth->createPermission('updateProject');
        $updateProject->description = 'Update project';
        $auth->add($updateProject);

        $deleteProject = $auth->createPermission('deleteProject');
        $deleteProject->description = 'Delete project';
        $auth->add($deleteProject);

        $assignEmployeeToProject = $auth->createPermission('assignEmployeeToProject');
        $assignEmployeeToProject->description = 'Assign employee to project';
        $auth->add($assignEmployeeToProject);

        // ============================================
        // Routes
        // ============================================

        $routeProjectsIndex = $auth->createPermission('/projects/index');
        $routeProjectsIndex->description = 'Route for viewing projects list';
        $auth->add($routeProjectsIndex);
        $auth->addChild($viewProjectsList, $routeProjectsIndex);

        $routeProjectsView = $auth->createPermission('/projects/view');
        $routeProjectsView->description = 'Route for viewing project details';
        $auth->add($routeProjectsView);
        $auth->addChild($viewProject, $routeProjectsView);

        $routeProjectsCreateAjax = $auth->createPermission('/projects/create-ajax');
        $routeProjectsCreateAjax->description = 'Route for creating project via ajax';
        $auth->add($routeProjectsCreateAjax);
        $auth->addChild($createProject, $routeProjectsCreateAjax);

        $routeProjectsUpdate = $auth->createPermission('/projects/update');
        $routeProjectsUpdate->description = 'Route for updating project';
        $auth->add($routeProjectsUpdate);
        $auth->addChild($updateProject, $routeProjectsUpdate);

        $routeProjectsGetEditOptions = $auth->createPermission('/projects/get-edit-options');
        $routeProjectsGetEditOptions->description = 'Route for getting project edit options';
        $auth->add($routeProjectsGetEditOptions);
        $auth->addChild($updateProject, $routeProjectsGetEditOptions);

        $routeProjectsSaveInlineEdit = $auth->createPermission('/projects/save-inline-edit');
        $routeProjectsSaveInlineEdit->description = 'Route for saving project inline edit';
        $auth->add($routeProjectsSaveInlineEdit);
        $auth->addChild($updateProject, $routeProjectsSaveInlineEdit);

        $routeProjectsDelete = $auth->createPermission('/projects/delete');
        $routeProjectsDelete->description = 'Route for deleting project';
        $auth->add($routeProjectsDelete);
        $auth->addChild($deleteProject, $routeProjectsDelete);

        $routeProjectsAssignEmployee = $auth->createPermission('/projects/assign-employee');
        $routeProjectsAssignEmployee->description = 'Route for assigning employee to project';
        $auth->add($routeProjectsAssignEmployee);
        $auth->addChild($assignEmployeeToProject, $routeProjectsAssignEmployee);

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin = $auth->getRole('super-administrator');
        $admin      = $auth->getRole('administrator');

        $permissions = [
            $viewProjectsList,
            $viewProject,
            $createProject,
            $updateProject,
            $deleteProject,
            $assignEmployeeToProject,
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
            '/projects/index',
            '/projects/view',
            '/projects/create-ajax',
            '/projects/update',
            '/projects/get-edit-options',
            '/projects/save-inline-edit',
            '/projects/delete',
            '/projects/assign-employee',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permissions = [
            'viewProjectsList',
            'viewProject',
            'createProject',
            'updateProject',
            'deleteProject',
            'assignEmployeeToProject',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
