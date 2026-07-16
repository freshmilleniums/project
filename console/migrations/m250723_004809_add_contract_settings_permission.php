<?php

use yii\db\Migration;

class m250723_004809_add_contract_settings_permission extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $permission = $auth->createPermission('accessContractSettings');
        $permission->description = 'Access Contract Settings';
        $auth->add($permission);

        $route = $auth->createPermission('/settings/contract-text');
        $route->description = 'Route to Contract Settings Page';
        $auth->add($route);

        $auth->addChild($permission, $route);

        $roles = ['super-administrator', 'administrator', 'local-administrator'];
        foreach ($roles as $roleName) {
            $role = $auth->getRole($roleName);
            if ($role) {
                $auth->addChild($role, $permission);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $permissions = [
            'accessContractSettings',
            '/settings/contract-text',
        ];

        foreach ($permissions as $name) {
            $perm = $auth->getPermission($name);
            if ($perm !== null) {
                $auth->remove($perm);
            }
        }
    }
}