<?php

use yii\db\Migration;

class m260921_214142_add_rbac_for_project_documents extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        if (!$auth->getPermission('/projects/download-document')) {
            $routeDownload = $auth->createPermission('/projects/download-document');
            $routeDownload->description = 'Route for downloading project document';
            $auth->add($routeDownload);

            $viewProject = $auth->getPermission('viewProject');
            if ($viewProject) {
                $auth->addChild($viewProject, $routeDownload);
            }
        }

        if (!$auth->getPermission('deleteProjectDocuments')) {
            $deleteProjectDocuments = $auth->createPermission('deleteProjectDocuments');
            $deleteProjectDocuments->description = 'Delete project documents';
            $auth->add($deleteProjectDocuments);
        } else {
            $deleteProjectDocuments = $auth->getPermission('deleteProjectDocuments');
        }

        if (!$auth->getPermission('/projects/delete-document')) {
            $routeDelete = $auth->createPermission('/projects/delete-document');
            $routeDelete->description = 'Route for deleting project document';
            $auth->add($routeDelete);
            $auth->addChild($deleteProjectDocuments, $routeDelete);
        }

        $superAdmin = $auth->getRole('super-administrator');
        $admin      = $auth->getRole('administrator');

        foreach ([$superAdmin, $admin] as $role) {
            if ($role && !$auth->hasChild($role, $deleteProjectDocuments)) {
                $auth->addChild($role, $deleteProjectDocuments);
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $routes = [
            '/projects/download-document',
            '/projects/delete-document',
        ];

        foreach ($routes as $route) {
            $permission = $auth->getPermission($route);
            if ($permission) {
                $auth->remove($permission);
            }
        }

        $permission = $auth->getPermission('deleteProjectDocuments');
        if ($permission) {
            $auth->remove($permission);
        }
    }
}
