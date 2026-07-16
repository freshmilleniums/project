<?php

use yii\db\Migration;

class m260612_233151_add_rbac_for_tasks extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions — create only if not exist
        // ============================================

        if (!$auth->getPermission('viewTasksList')) {
            $viewTasksList = $auth->createPermission('viewTasksList');
            $viewTasksList->description = 'View tasks list';
            $auth->add($viewTasksList);
        } else {
            $viewTasksList = $auth->getPermission('viewTasksList');
        }

        if (!$auth->getPermission('viewTask')) {
            $viewTask = $auth->createPermission('viewTask');
            $viewTask->description = 'View task details';
            $auth->add($viewTask);
        } else {
            $viewTask = $auth->getPermission('viewTask');
        }

        if (!$auth->getPermission('createTask')) {
            $createTask = $auth->createPermission('createTask');
            $createTask->description = 'Create task';
            $auth->add($createTask);
        } else {
            $createTask = $auth->getPermission('createTask');
        }

        if (!$auth->getPermission('updateTask')) {
            $updateTask = $auth->createPermission('updateTask');
            $updateTask->description = 'Update task';
            $auth->add($updateTask);
        } else {
            $updateTask = $auth->getPermission('updateTask');
        }

        if (!$auth->getPermission('deleteTask')) {
            $deleteTask = $auth->createPermission('deleteTask');
            $deleteTask->description = 'Delete task';
            $auth->add($deleteTask);
        } else {
            $deleteTask = $auth->getPermission('deleteTask');
        }

        if (!$auth->getPermission('manageTaskDocuments')) {
            $manageTaskDocuments = $auth->createPermission('manageTaskDocuments');
            $manageTaskDocuments->description = 'Download and delete task documents';
            $auth->add($manageTaskDocuments);
        } else {
            $manageTaskDocuments = $auth->getPermission('manageTaskDocuments');
        }

        // ============================================
        // Routes — create only if not exist
        // ============================================

        if (!$auth->getPermission('/tasks/index')) {
            $routeTasksIndex = $auth->createPermission('/tasks/index');
            $routeTasksIndex->description = 'Route for viewing tasks list';
            $auth->add($routeTasksIndex);
            $auth->addChild($viewTasksList, $routeTasksIndex);
        }

        if (!$auth->getPermission('/tasks/view')) {
            $routeTasksView = $auth->createPermission('/tasks/view');
            $routeTasksView->description = 'Route for viewing task details';
            $auth->add($routeTasksView);
            $auth->addChild($viewTask, $routeTasksView);
        }

        if (!$auth->getPermission('/tasks/create-ajax')) {
            $routeTasksCreateAjax = $auth->createPermission('/tasks/create-ajax');
            $routeTasksCreateAjax->description = 'Route for creating task via ajax';
            $auth->add($routeTasksCreateAjax);
            $auth->addChild($createTask, $routeTasksCreateAjax);
        }

        if (!$auth->getPermission('/tasks/update')) {
            $routeTasksUpdate = $auth->createPermission('/tasks/update');
            $routeTasksUpdate->description = 'Route for updating task';
            $auth->add($routeTasksUpdate);
            $auth->addChild($updateTask, $routeTasksUpdate);
        }

        if (!$auth->getPermission('/tasks/delete')) {
            $routeTasksDelete = $auth->createPermission('/tasks/delete');
            $routeTasksDelete->description = 'Route for deleting task';
            $auth->add($routeTasksDelete);
            $auth->addChild($deleteTask, $routeTasksDelete);
        }

        if (!$auth->getPermission('/tasks/download-document')) {
            $routeTasksDownloadDocument = $auth->createPermission('/tasks/download-document');
            $routeTasksDownloadDocument->description = 'Route for downloading task document';
            $auth->add($routeTasksDownloadDocument);
            $auth->addChild($manageTaskDocuments, $routeTasksDownloadDocument);
        }

        if (!$auth->getPermission('/tasks/delete-document')) {
            $routeTasksDeleteDocument = $auth->createPermission('/tasks/delete-document');
            $routeTasksDeleteDocument->description = 'Route for deleting task document';
            $auth->add($routeTasksDeleteDocument);
            $auth->addChild($manageTaskDocuments, $routeTasksDeleteDocument);
        }

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin        = $auth->getRole('super-administrator');
        $admin             = $auth->getRole('administrator');
        $emailTaskOperator = $auth->getRole('email-task-operator');

        $permissions = [
            $viewTasksList,
            $viewTask,
            $createTask,
            $updateTask,
            $deleteTask,
            $manageTaskDocuments,
        ];

        foreach ([$superAdmin, $admin, $emailTaskOperator] as $role) {
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
            '/tasks/index',
            '/tasks/view',
            '/tasks/create-ajax',
            '/tasks/update',
            '/tasks/delete',
            '/tasks/download-document',
            '/tasks/delete-document',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permissions = [
            'viewTasksList',
            'viewTask',
            'createTask',
            'updateTask',
            'deleteTask',
            'manageTaskDocuments',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
