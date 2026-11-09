<?php

use yii\db\Migration;

class m261107_014720_move_task_search_routes_to_tasks extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $addTaskToUser = $auth->getPermission('addTaskToUser');

        // Remove old routes (actions moved to TasksController)
        $routes = [
            '/users/search-tasks',
            '/users/get-task-data',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        // Routes — tasks
        $routeTasksSearchTasks = $auth->createPermission('/tasks/search-tasks');
        $routeTasksSearchTasks->description = 'Route for searching tasks and task templates';
        $auth->add($routeTasksSearchTasks);
        $auth->addChild($addTaskToUser, $routeTasksSearchTasks);

        $routeTasksGetTaskData = $auth->createPermission('/tasks/get-task-data');
        $routeTasksGetTaskData->description = 'Route for getting task or task template data';
        $auth->add($routeTasksGetTaskData);
        $auth->addChild($addTaskToUser, $routeTasksGetTaskData);
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $addTaskToUser = $auth->getPermission('addTaskToUser');

        $routes = [
            '/tasks/search-tasks',
            '/tasks/get-task-data',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $routeUsersSearchTasks = $auth->createPermission('/users/search-tasks');
        $routeUsersSearchTasks->description = 'Route for searching tasks in add task modal';
        $auth->add($routeUsersSearchTasks);
        $auth->addChild($addTaskToUser, $routeUsersSearchTasks);

        $routeUsersGetTaskData = $auth->createPermission('/users/get-task-data');
        $routeUsersGetTaskData->description = 'Route for getting task data in add task modal';
        $auth->add($routeUsersGetTaskData);
        $auth->addChild($addTaskToUser, $routeUsersGetTaskData);
    }
}
