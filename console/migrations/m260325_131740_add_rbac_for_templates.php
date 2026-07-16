<?php

use yii\db\Migration;

class m260325_131740_add_rbac_for_templates extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;

        // Create action permissions
        $viewTemplatesList = $auth->createPermission('viewTemplatesList');
        $viewTemplatesList->description = 'View templates list';
        $auth->add($viewTemplatesList);

        $viewTemplate = $auth->createPermission('viewTemplate');
        $viewTemplate->description = 'View template details';
        $auth->add($viewTemplate);

        $createTemplate = $auth->createPermission('createTemplate');
        $createTemplate->description = 'Create new template';
        $auth->add($createTemplate);

        $updateTemplate = $auth->createPermission('updateTemplate');
        $updateTemplate->description = 'Update template';
        $auth->add($updateTemplate);

        $deleteTemplate = $auth->createPermission('deleteTemplate');
        $deleteTemplate->description = 'Delete template';
        $auth->add($deleteTemplate);

        $sendTemplate = $auth->createPermission('sendTemplate');
        $sendTemplate->description = 'Send template to employee';
        $auth->add($sendTemplate);

        $manageTemplateDocuments = $auth->createPermission('manageTemplateDocuments');
        $manageTemplateDocuments->description = 'Manage template documents';
        $auth->add($manageTemplateDocuments);

        // Create route permissions
        $routeIndex = $auth->createPermission('/templates/index');
        $routeIndex->description = 'Route for viewing templates list';
        $auth->add($routeIndex);
        $auth->addChild($viewTemplatesList, $routeIndex);

        $routeView = $auth->createPermission('/templates/view');
        $routeView->description = 'Route for viewing template details';
        $auth->add($routeView);
        $auth->addChild($viewTemplate, $routeView);

        $routeCreateAjax = $auth->createPermission('/templates/create-ajax');
        $routeCreateAjax->description = 'Route for creating template';
        $auth->add($routeCreateAjax);
        $auth->addChild($createTemplate, $routeCreateAjax);

        $routeUpdate = $auth->createPermission('/templates/update');
        $routeUpdate->description = 'Route for updating template';
        $auth->add($routeUpdate);
        $auth->addChild($updateTemplate, $routeUpdate);

        $routeDelete = $auth->createPermission('/templates/delete');
        $routeDelete->description = 'Route for deleting template';
        $auth->add($routeDelete);
        $auth->addChild($deleteTemplate, $routeDelete);

        $routePreview = $auth->createPermission('/templates/preview');
        $routePreview->description = 'Route for previewing template with macros';
        $auth->add($routePreview);
        $auth->addChild($viewTemplate, $routePreview);

        $routeSendToEmployee = $auth->createPermission('/templates/send-to-employee');
        $routeSendToEmployee->description = 'Route for sending template to employee';
        $auth->add($routeSendToEmployee);
        $auth->addChild($sendTemplate, $routeSendToEmployee);

        $routeShowSendForm = $auth->createPermission('/templates/show-send-form');
        $routeShowSendForm->description = 'Route for showing send template form';
        $auth->add($routeShowSendForm);
        $auth->addChild($sendTemplate, $routeShowSendForm);

        $routeDownloadDocument = $auth->createPermission('/templates/download-document');
        $routeDownloadDocument->description = 'Route for downloading template document';
        $auth->add($routeDownloadDocument);
        $auth->addChild($manageTemplateDocuments, $routeDownloadDocument);

        $routeDeleteDocument = $auth->createPermission('/templates/delete-document');
        $routeDeleteDocument->description = 'Route for deleting template document';
        $auth->add($routeDeleteDocument);
        $auth->addChild($manageTemplateDocuments, $routeDeleteDocument);

        // Assign permissions to roles
        $superAdmin = $auth->getRole('super-administrator');
        $admin = $auth->getRole('administrator');
        $emailTaskOperator = $auth->getRole('email-task-operator');

        $permissions = [
            $viewTemplatesList,
            $viewTemplate,
            $createTemplate,
            $updateTemplate,
            $deleteTemplate,
            $sendTemplate,
            $manageTemplateDocuments,
        ];

        if ($superAdmin) {
            foreach ($permissions as $permission) {
                if ($permission && !$auth->hasChild($superAdmin, $permission)) {
                    $auth->addChild($superAdmin, $permission);
                }
            }
        }

        if ($admin) {
            foreach ($permissions as $permission) {
                if ($permission && !$auth->hasChild($admin, $permission)) {
                    $auth->addChild($admin, $permission);
                }
            }
        }

        if ($emailTaskOperator) {
            foreach ($permissions as $permission) {
                if ($permission && !$auth->hasChild($emailTaskOperator, $permission)) {
                    $auth->addChild($emailTaskOperator, $permission);
                }
            }
        }
    }

    public function safeDown()
    {
        $auth = Yii::$app->authManager;

        $routes = [
            '/templates/index',
            '/templates/view',
            '/templates/create-ajax',
            '/templates/update',
            '/templates/delete',
            '/templates/preview',
            '/templates/send-to-employee',
            '/templates/show-send-form',
            '/templates/download-document',
            '/templates/delete-document',
        ];

        foreach ($routes as $route) {
            $routePermission = $auth->getPermission($route);
            if ($routePermission) {
                $auth->remove($routePermission);
            }
        }

        $permissions = [
            'viewTemplatesList',
            'viewTemplate',
            'createTemplate',
            'updateTemplate',
            'deleteTemplate',
            'sendTemplate',
            'manageTemplateDocuments',
        ];

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->remove($permission);
            }
        }
    }
}
