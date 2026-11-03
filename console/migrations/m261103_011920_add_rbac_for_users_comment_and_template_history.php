<?php

use yii\db\Migration;

class m261103_011920_add_rbac_for_users_comment_and_template_history extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $viewUser = $auth->getPermission('viewUser');

        if (!$auth->getPermission('/users/save-comment')) {
            $routeUsersSaveComment = $auth->createPermission('/users/save-comment');
            $routeUsersSaveComment->description = 'Route for saving inline comment on employee';
            $auth->add($routeUsersSaveComment);
            if ($viewUser) {
                $auth->addChild($viewUser, $routeUsersSaveComment);
            }
        }

        if (!$auth->getPermission('/users/template-history')) {
            $routeUsersTemplateHistory = $auth->createPermission('/users/template-history');
            $routeUsersTemplateHistory->description = 'Route for viewing template send history for employee';
            $auth->add($routeUsersTemplateHistory);
            if ($viewUser) {
                $auth->addChild($viewUser, $routeUsersTemplateHistory);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        foreach (['/users/save-comment', '/users/template-history'] as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
