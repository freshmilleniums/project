<?php

use yii\db\Migration;

class m261117_015705_add_search_chats_route_to_internal_chat extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        $internalChat = $auth->getPermission('InternalChat');
        if (!$internalChat) {
            return false;
        }

        $route = '/chat/search-chats';

        $permission = $auth->getPermission($route);
        if (!$permission) {
            $permission = $auth->createPermission($route);
            $permission->description = 'Route: ' . $route;
            $auth->add($permission);
        }

        if (!$auth->hasChild($internalChat, $permission)) {
            $auth->addChild($internalChat, $permission);
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $permission = $auth->getPermission('/chat/search-chats');
        if ($permission) {
            $auth->remove($permission);
        }
    }
}
