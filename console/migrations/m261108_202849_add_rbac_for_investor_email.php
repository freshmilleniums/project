<?php

use yii\db\Migration;

class m261108_202849_add_rbac_for_investor_email extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        if (!$auth->getPermission('sendInvestorEmail')) {
            $sendInvestorEmail = $auth->createPermission('sendInvestorEmail');
            $sendInvestorEmail->description = 'Send email from investor SMTP';
            $auth->add($sendInvestorEmail);
        } else {
            $sendInvestorEmail = $auth->getPermission('sendInvestorEmail');
        }

        if (!$auth->getPermission('/investors/send-email-ajax')) {
            $routeSendEmailAjax = $auth->createPermission('/investors/send-email-ajax');
            $routeSendEmailAjax->description = 'Route for loading investor email form';
            $auth->add($routeSendEmailAjax);
            $auth->addChild($sendInvestorEmail, $routeSendEmailAjax);
        }

        if (!$auth->getPermission('/investors/send-email')) {
            $routeSendEmail = $auth->createPermission('/investors/send-email');
            $routeSendEmail->description = 'Route for sending email from investor';
            $auth->add($routeSendEmail);
            $auth->addChild($sendInvestorEmail, $routeSendEmail);
        }

        $superAdmin = $auth->getRole('super-administrator');
        $admin      = $auth->getRole('administrator');
        $emailOperator = $auth->getRole('email-task-operator');

        foreach ([$superAdmin, $admin, $emailOperator] as $role) {
            if ($role && $sendInvestorEmail && !$auth->hasChild($role, $sendInvestorEmail)) {
                $auth->addChild($role, $sendInvestorEmail);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        foreach (['/investors/send-email-ajax', '/investors/send-email'] as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permission = $auth->getPermission('sendInvestorEmail');
        if ($permission) {
            $auth->remove($permission);
        }
    }
}
