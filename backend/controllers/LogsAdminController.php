<?php

namespace backend\controllers;

use Yii;
use common\models\LogsAdmin;
use backend\models\LogsAdminSearch;
use yii\web\Controller;
use yii\filters\VerbFilter;

/**
 * LogsAdminController implements listing of admin logs.
 */
class LogsAdminController extends BaseController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    // No specific verb restrictions for index
                ],
            ],
        ];
    }

    /**
     * Lists all LogsAdmin models.
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new LogsAdminSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // Eager load user relation
        $dataProvider->query->with('user');

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
}
