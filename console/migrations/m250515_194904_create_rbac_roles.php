<?php

use yii\db\Migration;

/**
 * Creates initial RBAC roles for the system
 */
class m250515_194904_create_rbac_roles extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $roles = [
            'super-administrator' => 'Super Administrator',
            'administrator' => 'Administrator',
            'phone-operator' => 'Phone Operator',
            'email-task-operator' => 'Email/Task Operator',
            'employee' => 'Employee',
        ];

        foreach ($roles as $name => $description) {
            $role = $auth->createRole($name);
            $role->description = $description;
            $auth->add($role);
        }

        return true;
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $roles = [
            'super-administrator',
            'administrator',
            'phone-operator',
            'email-task-operator',
            'employee',
        ];

        foreach ($roles as $roleName) {
            $role = $auth->getRole($roleName);
            if ($role) {
                $auth->remove($role);
            }
        }

        return true;
    }
}