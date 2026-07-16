<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use backend\models\NotificationModel;
use common\services\NotificationService;

/**
 * NotificationController implements the CRUD actions for NotificationModel.
 */
class NotificationController extends BaseController
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Only for authenticated users
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all NotificationModel models for current user.
     * @return mixed
     */
    public function actionIndex()
    {
        $userId = Yii::$app->user->id;

        // Create ActiveDataProvider for pagination
        $dataProvider = new ActiveDataProvider([
            'query' => NotificationModel::find()
                ->where(['user_id' => $userId])
                ->orderBy(['created_at' => SORT_DESC]), // Newest first
            'pagination' => [
                'pageSize' => 20, // 20 notifications per page
            ],
        ]);

        // Get current page notifications and mark unread ones as read
        $notifications = $dataProvider->getModels();
        $unreadIds = [];

        foreach ($notifications as $notification) {
            if ($notification->isUnread()) {
                $unreadIds[] = $notification->id;
            }
        }

        // Update only displayed unread notifications
        if (!empty($unreadIds)) {
            NotificationModel::updateAll(
                ['read' => 1],
                ['id' => $unreadIds]
            );
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
}