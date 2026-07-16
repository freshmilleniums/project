<?php

use yii\db\Migration;

class m260603_011236_add_rbac_for_chat extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // ============================================
        // Employee Chat (my-chat)
        // ============================================

        $employeeChat = $auth->createPermission('EmployeeChat');
        $employeeChat->description = 'Access employee personal chat';
        $auth->add($employeeChat);

        $employeeChatRoutes = [
            '/my-chat/index',
            '/my-chat/send-message',
            '/my-chat/edit-message',
            '/my-chat/download-attachment',
            '/my-chat/mark-as-read',
            '/my-chat/load-more-messages',
            '/my-chat/get-latest-messages',
            '/my-chat/get-unread-count',
            '/my-chat/search-messages',
            '/my-chat/generate-websocket-token',
        ];

        foreach ($employeeChatRoutes as $route) {
            $permission = $auth->createPermission($route);
            $permission->description = 'Route: ' . $route;
            $auth->add($permission);
            $auth->addChild($employeeChat, $permission);
        }

        $employee = $auth->getRole('employee');
        if ($employee) {
            $auth->addChild($employee, $employeeChat);
        }

        // ============================================
        // Internal Chat - staff/operators 
        // ============================================

        $internalChat = $auth->createPermission('InternalChat');
        $internalChat->description = 'Access internal chat system for staff and operators';
        $auth->add($internalChat);

        $internalChatRoutes = [
            '/chat/index',
            '/chat/send-message',
            '/chat/edit-message',
            '/chat/download-attachment',
            '/chat/mark-as-read',
            '/chat/load-more-messages',
            '/chat/get-unread-count',
            '/chat/search-messages',
            '/chat/generate-websocket-token',
            '/chat/create-chat',
            '/chat/add-participants',
            '/chat/remove-participant',
            '/chat/admin-chat',
        ];

        foreach ($internalChatRoutes as $route) {
            $permission = $auth->createPermission($route);
            $permission->description = 'Route: ' . $route;
            $auth->add($permission);
            $auth->addChild($internalChat, $permission);
        }

        $superAdmin = $auth->getRole('super-administrator');
        $admin = $auth->getRole('administrator');
        $emailTaskOperator = $auth->getRole('email-task-operator');

        foreach ([$superAdmin, $admin, $emailTaskOperator] as $role) {
            if ($role && !$auth->hasChild($role, $internalChat)) {
                $auth->addChild($role, $internalChat);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $employeeChatRoutes = [
            '/my-chat/index',
            '/my-chat/send-message',
            '/my-chat/edit-message',
            '/my-chat/download-attachment',
            '/my-chat/mark-as-read',
            '/my-chat/load-more-messages',
            '/my-chat/get-latest-messages',
            '/my-chat/get-unread-count',
            '/my-chat/search-messages',
            '/my-chat/generate-websocket-token',
        ];

        $internalChatRoutes = [
            '/chat/index',
            '/chat/send-message',
            '/chat/edit-message',
            '/chat/download-attachment',
            '/chat/mark-as-read',
            '/chat/load-more-messages',
            '/chat/get-unread-count',
            '/chat/search-messages',
            '/chat/generate-websocket-token',
            '/chat/create-chat',
            '/chat/add-participants',
            '/chat/remove-participant',
            '/chat/admin-chat',
        ];

        foreach (array_merge($employeeChatRoutes, $internalChatRoutes) as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        foreach (['EmployeeChat', 'InternalChat'] as $permName) {
            $permission = $auth->getPermission($permName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
