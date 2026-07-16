<?php

use yii\db\Migration;

class m260612_231630_add_rbac_for_users extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Permissions
        // ============================================

        if (!$auth->getPermission('viewUsersList')) {
            $viewUsersList = $auth->createPermission('viewUsersList');
            $viewUsersList->description = 'View users list';
            $auth->add($viewUsersList);
        } else {
            $viewUsersList = $auth->getPermission('viewUsersList');
        }

        if (!$auth->getPermission('viewUser')) {
            $viewUser = $auth->createPermission('viewUser');
            $viewUser->description = 'View user details';
            $auth->add($viewUser);
        } else {
            $viewUser = $auth->getPermission('viewUser');
        }

        if (!$auth->getPermission('viewCallCenterCard')) {
            $viewCallCenterCard = $auth->createPermission('viewCallCenterCard');
            $viewCallCenterCard->description = 'View user card in call center';
            $auth->add($viewCallCenterCard);
        } else {
            $viewCallCenterCard = $auth->getPermission('viewCallCenterCard');
        }

        if (!$auth->getPermission('createUser')) {
            $createUser = $auth->createPermission('createUser');
            $createUser->description = 'Create user';
            $auth->add($createUser);
        } else {
            $createUser = $auth->getPermission('createUser');
        }

        if (!$auth->getPermission('updateUser')) {
            $updateUser = $auth->createPermission('updateUser');
            $updateUser->description = 'Update user';
            $auth->add($updateUser);
        } else {
            $updateUser = $auth->getPermission('updateUser');
        }

        if (!$auth->getPermission('deleteUser')) {
            $deleteUser = $auth->createPermission('deleteUser');
            $deleteUser->description = 'Delete (archive) user';
            $auth->add($deleteUser);
        } else {
            $deleteUser = $auth->getPermission('deleteUser');
        }

        if (!$auth->getPermission('changeUserStatus')) {
            $changeUserStatus = $auth->createPermission('changeUserStatus');
            $changeUserStatus->description = 'Change user substatus';
            $auth->add($changeUserStatus);
        } else {
            $changeUserStatus = $auth->getPermission('changeUserStatus');
        }

        if (!$auth->getPermission('changeUserPassword')) {
            $changeUserPassword = $auth->createPermission('changeUserPassword');
            $changeUserPassword->description = 'Change user password';
            $auth->add($changeUserPassword);
        } else {
            $changeUserPassword = $auth->getPermission('changeUserPassword');
        }

        if (!$auth->getPermission('addTaskToUser')) {
            $addTaskToUser = $auth->createPermission('addTaskToUser');
            $addTaskToUser->description = 'Add task to user';
            $auth->add($addTaskToUser);
        } else {
            $addTaskToUser = $auth->getPermission('addTaskToUser');
        }

        if (!$auth->getPermission('addProjectToUser')) {
            $addProjectToUser = $auth->createPermission('addProjectToUser');
            $addProjectToUser->description = 'Add project to user';
            $auth->add($addProjectToUser);
        } else {
            $addProjectToUser = $auth->getPermission('addProjectToUser');
        }

        if (!$auth->getPermission('addInvestorToUser')) {
            $addInvestorToUser = $auth->createPermission('addInvestorToUser');
            $addInvestorToUser->description = 'Add investor to user';
            $auth->add($addInvestorToUser);
        } else {
            $addInvestorToUser = $auth->getPermission('addInvestorToUser');
        }

        if (!$auth->getPermission('sendTemplateToUser')) {
            $sendTemplateToUser = $auth->createPermission('sendTemplateToUser');
            $sendTemplateToUser->description = 'Send template to user';
            $auth->add($sendTemplateToUser);
        } else {
            $sendTemplateToUser = $auth->getPermission('sendTemplateToUser');
        }

        if (!$auth->getPermission('viewUsersArchive')) {
            $viewUsersArchive = $auth->createPermission('viewUsersArchive');
            $viewUsersArchive->description = 'View users archive';
            $auth->add($viewUsersArchive);
        } else {
            $viewUsersArchive = $auth->getPermission('viewUsersArchive');
        }

        if (!$auth->getPermission('quickSearchUsers')) {
            $quickSearchUsers = $auth->createPermission('quickSearchUsers');
            $quickSearchUsers->description = 'Quick search users by name, email or phone';
            $auth->add($quickSearchUsers);
        } else {
            $quickSearchUsers = $auth->getPermission('quickSearchUsers');
        }

        if (!$auth->getPermission('getUsersLookup')) {
            $getUsersLookup = $auth->createPermission('getUsersLookup');
            $getUsersLookup->description = 'Get users/workers lists for dropdowns and chat';
            $auth->add($getUsersLookup);
        } else {
            $getUsersLookup = $auth->getPermission('getUsersLookup');
        }

        $assignInvestorToEmployee = $auth->getPermission('assignInvestorToEmployee');

        // ============================================
        // Routes
        // ============================================

        // Already exist in DB — ensure correct parent binding
        foreach ([
                     '/users/index'  => $viewUsersList,
                     '/users/view'   => $viewUser,
                     '/users/create' => $createUser,
                     '/users/update' => $updateUser,
                     '/users/delete' => $deleteUser,
                 ] as $route => $permission) {
            $routePermission = $auth->getPermission($route);
            if ($routePermission && !$auth->hasChild($permission, $routePermission)) {
                $auth->addChild($permission, $routePermission);
            }
        }

        if (!$auth->getPermission('/users/for-call-center-view')) {
            $routeUsersForCallCenterView = $auth->createPermission('/users/for-call-center-view');
            $routeUsersForCallCenterView->description = 'Route for viewing user card in call center';
            $auth->add($routeUsersForCallCenterView);
            $auth->addChild($viewCallCenterCard, $routeUsersForCallCenterView);
        }

        if (!$auth->getPermission('/users/ajax-validation')) {
            $routeUsersAjaxValidation = $auth->createPermission('/users/ajax-validation');
            $routeUsersAjaxValidation->description = 'Route for ajax validation of user form';
            $auth->add($routeUsersAjaxValidation);
            $auth->addChild($updateUser, $routeUsersAjaxValidation);
        }

        if (!$auth->getPermission('/users/remove-employer')) {
            $routeUsersRemoveEmployer = $auth->createPermission('/users/remove-employer');
            $routeUsersRemoveEmployer->description = 'Route for archiving employer';
            $auth->add($routeUsersRemoveEmployer);
            $auth->addChild($deleteUser, $routeUsersRemoveEmployer);
        }

        if (!$auth->getPermission('/users/restore-employer')) {
            $routeUsersRestoreEmployer = $auth->createPermission('/users/restore-employer');
            $routeUsersRestoreEmployer->description = 'Route for restoring employer';
            $auth->add($routeUsersRestoreEmployer);
            $auth->addChild($deleteUser, $routeUsersRestoreEmployer);
        }

        if (!$auth->getPermission('/users/change-status')) {
            $routeUsersChangeStatus = $auth->createPermission('/users/change-status');
            $routeUsersChangeStatus->description = 'Route for changing user substatus';
            $auth->add($routeUsersChangeStatus);
            $auth->addChild($changeUserStatus, $routeUsersChangeStatus);
        }

        if (!$auth->getPermission('/users/change-password')) {
            $routeUsersChangePassword = $auth->createPermission('/users/change-password');
            $routeUsersChangePassword->description = 'Route for changing user password';
            $auth->add($routeUsersChangePassword);
            $auth->addChild($changeUserPassword, $routeUsersChangePassword);
        }

        if (!$auth->getPermission('/users/add-task')) {
            $routeUsersAddTask = $auth->createPermission('/users/add-task');
            $routeUsersAddTask->description = 'Route for adding task to user';
            $auth->add($routeUsersAddTask);
            $auth->addChild($addTaskToUser, $routeUsersAddTask);
        }

        if (!$auth->getPermission('/users/add-project')) {
            $routeUsersAddProject = $auth->createPermission('/users/add-project');
            $routeUsersAddProject->description = 'Route for adding project to user';
            $auth->add($routeUsersAddProject);
            $auth->addChild($addProjectToUser, $routeUsersAddProject);
        }

        if (!$auth->getPermission('/users/add-investor')) {
            $routeUsersAddInvestor = $auth->createPermission('/users/add-investor');
            $routeUsersAddInvestor->description = 'Route for adding investor to user';
            $auth->add($routeUsersAddInvestor);
            $auth->addChild($addInvestorToUser, $routeUsersAddInvestor);
        }

        if (!$auth->getPermission('/users/assign-investors')) {
            $routeUsersAssignInvestors = $auth->createPermission('/users/assign-investors');
            $routeUsersAssignInvestors->description = 'Route for bulk assigning investors to employees';
            $auth->add($routeUsersAssignInvestors);
            if ($assignInvestorToEmployee) {
                $auth->addChild($assignInvestorToEmployee, $routeUsersAssignInvestors);
            }
        }

        if (!$auth->getPermission('/users/send-template')) {
            $routeUsersSendTemplate = $auth->createPermission('/users/send-template');
            $routeUsersSendTemplate->description = 'Route for sending template to user';
            $auth->add($routeUsersSendTemplate);
            $auth->addChild($sendTemplateToUser, $routeUsersSendTemplate);
        }

        if (!$auth->getPermission('/users/archive')) {
            $routeUsersArchive = $auth->createPermission('/users/archive');
            $routeUsersArchive->description = 'Route for viewing users archive';
            $auth->add($routeUsersArchive);
            $auth->addChild($viewUsersArchive, $routeUsersArchive);
        }

        if (!$auth->getPermission('/users/quick-search')) {
            $routeUsersQuickSearch = $auth->createPermission('/users/quick-search');
            $routeUsersQuickSearch->description = 'Route for quick search users';
            $auth->add($routeUsersQuickSearch);
            $auth->addChild($quickSearchUsers, $routeUsersQuickSearch);
        }

        if (!$auth->getPermission('/users/get-workers')) {
            $routeUsersGetWorkers = $auth->createPermission('/users/get-workers');
            $routeUsersGetWorkers->description = 'Route for getting workers list for dropdowns';
            $auth->add($routeUsersGetWorkers);
            $auth->addChild($getUsersLookup, $routeUsersGetWorkers);
        }

        if (!$auth->getPermission('/users/get-users')) {
            $routeUsersGetUsers = $auth->createPermission('/users/get-users');
            $routeUsersGetUsers->description = 'Route for getting users list for chat';
            $auth->add($routeUsersGetUsers);
            $auth->addChild($getUsersLookup, $routeUsersGetUsers);
        }

        // ============================================
        // Role assignments
        // ============================================

        $superAdmin        = $auth->getRole('super-administrator');
        $admin             = $auth->getRole('administrator');
        $phoneOperator     = $auth->getRole('phone-operator');
        $emailTaskOperator = $auth->getRole('email-task-operator');

        $superAdminPermissions = [
            $viewUsersList, $viewUser, $viewCallCenterCard,
            $createUser, $updateUser, $deleteUser,
            $changeUserStatus, $changeUserPassword,
            $addTaskToUser, $addProjectToUser, $addInvestorToUser,
            $sendTemplateToUser, $viewUsersArchive,
            $quickSearchUsers, $getUsersLookup,
        ];

        $adminPermissions = [
            $viewUsersList, $viewUser, $viewCallCenterCard,
            $createUser, $updateUser, $deleteUser,
            $changeUserStatus,
            $addTaskToUser, $addProjectToUser, $addInvestorToUser,
            $sendTemplateToUser, $viewUsersArchive,
            $quickSearchUsers, $getUsersLookup,
        ];

        $phoneOperatorPermissions = [
            $viewUsersList, $viewUser, $viewCallCenterCard,
            $changeUserStatus, $sendTemplateToUser,
            $quickSearchUsers, $getUsersLookup,
        ];

        $emailTaskOperatorPermissions = [
            $viewUsersList, $viewUser,
            $addTaskToUser, $sendTemplateToUser,
            $getUsersLookup,
        ];

        $rolePermissions = [
            [$superAdmin,        $superAdminPermissions],
            [$admin,             $adminPermissions],
            [$phoneOperator,     $phoneOperatorPermissions],
            [$emailTaskOperator, $emailTaskOperatorPermissions],
        ];

        foreach ($rolePermissions as [$role, $permissions]) {
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

        $newRoutes = [
            '/users/for-call-center-view',
            '/users/ajax-validation',
            '/users/remove-employer',
            '/users/restore-employer',
            '/users/change-status',
            '/users/change-password',
            '/users/add-task',
            '/users/add-project',
            '/users/add-investor',
            '/users/assign-investors',
            '/users/send-template',
            '/users/archive',
            '/users/quick-search',
            '/users/get-workers',
            '/users/get-users',
        ];

        foreach ($newRoutes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $newPermissions = [
            'viewUsersList',
            'viewCallCenterCard',
            'changeUserStatus',
            'changeUserPassword',
            'addTaskToUser',
            'addProjectToUser',
            'addInvestorToUser',
            'sendTemplateToUser',
            'viewUsersArchive',
            'quickSearchUsers',
            'getUsersLookup',
        ];

        foreach ($newPermissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
