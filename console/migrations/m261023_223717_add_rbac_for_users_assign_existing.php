<?php

use yii\db\Migration;

class m261023_223717_add_rbac_for_users_assign_existing extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $addTaskToUser     = $auth->getPermission('addTaskToUser');
        $addInvestorToUser = $auth->getPermission('addInvestorToUser');
        $addProjectToUser  = $auth->getPermission('addProjectToUser');

        // ============================================
        // Routes — tasks
        // ============================================

        $routeUsersSearchTasks = $auth->createPermission('/users/search-tasks');
        $routeUsersSearchTasks->description = 'Route for searching tasks in add task modal';
        $auth->add($routeUsersSearchTasks);
        $auth->addChild($addTaskToUser, $routeUsersSearchTasks);

        $routeUsersGetTaskData = $auth->createPermission('/users/get-task-data');
        $routeUsersGetTaskData->description = 'Route for getting task data in add task modal';
        $auth->add($routeUsersGetTaskData);
        $auth->addChild($addTaskToUser, $routeUsersGetTaskData);

        $routeUsersAssignExistingTask = $auth->createPermission('/users/assign-existing-task');
        $routeUsersAssignExistingTask->description = 'Route for reassigning existing task to employee';
        $auth->add($routeUsersAssignExistingTask);
        $auth->addChild($addTaskToUser, $routeUsersAssignExistingTask);

        // ============================================
        // Routes — investors
        // ============================================

        $routeUsersSearchInvestors = $auth->createPermission('/users/search-investors');
        $routeUsersSearchInvestors->description = 'Route for searching investors in add investor modal';
        $auth->add($routeUsersSearchInvestors);
        $auth->addChild($addInvestorToUser, $routeUsersSearchInvestors);

        $routeUsersGetInvestorData = $auth->createPermission('/users/get-investor-data');
        $routeUsersGetInvestorData->description = 'Route for getting investor data in add investor modal';
        $auth->add($routeUsersGetInvestorData);
        $auth->addChild($addInvestorToUser, $routeUsersGetInvestorData);

        $routeUsersAssignExistingInvestor = $auth->createPermission('/users/assign-existing-investor');
        $routeUsersAssignExistingInvestor->description = 'Route for assigning existing investor to employee';
        $auth->add($routeUsersAssignExistingInvestor);
        $auth->addChild($addInvestorToUser, $routeUsersAssignExistingInvestor);

        // ============================================
        // Routes — projects
        // ============================================

        $routeUsersSearchProjects = $auth->createPermission('/users/search-projects');
        $routeUsersSearchProjects->description = 'Route for searching projects in add project modal';
        $auth->add($routeUsersSearchProjects);
        $auth->addChild($addProjectToUser, $routeUsersSearchProjects);

        $routeUsersGetProjectData = $auth->createPermission('/users/get-project-data');
        $routeUsersGetProjectData->description = 'Route for getting project data in add project modal';
        $auth->add($routeUsersGetProjectData);
        $auth->addChild($addProjectToUser, $routeUsersGetProjectData);

        $routeUsersAssignExistingProject = $auth->createPermission('/users/assign-existing-project');
        $routeUsersAssignExistingProject->description = 'Route for assigning existing project to employee';
        $auth->add($routeUsersAssignExistingProject);
        $auth->addChild($addProjectToUser, $routeUsersAssignExistingProject);
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $routes = [
            '/users/search-tasks',
            '/users/get-task-data',
            '/users/assign-existing-task',
            '/users/search-investors',
            '/users/get-investor-data',
            '/users/assign-existing-investor',
            '/users/search-projects',
            '/users/get-project-data',
            '/users/assign-existing-project',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
