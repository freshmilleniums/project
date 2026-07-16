<?php

namespace backend\controllers;

use common\models\LoginForm;
use Yii;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use common\services\UserService;
use common\services\NotificationService;
use common\services\CallStatisticsService;
use common\services\DashboardStatisticsService;
use yii\helpers\Url;
use backend\models\User;
use backend\models\Chat;
use common\services\EmployersDashboardStatisticsService;

/**
 * Site controller
 */
class SiteController extends BaseController
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
                        'actions' => ['login', 'error','signup'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout', 'index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRolesByUser(Yii::$app->user->id);

        if (isset($roles['super-administrator'])) {
            $stats = new EmployersDashboardStatisticsService();

            return $this->render('dashboard_super_administrator', [
                'crmStats'             => $stats->getCrmStatistics(),
                'employeeStatusCounts' => $stats->getEmployeeStatusCounts(),
                'taskStats'            => $stats->getTaskStatistics(),
                'administratorsOverview' => $stats->getAdministratorsOverview(),
                'callCenterLoad'       => $stats->getCallCenterOperatorsLoad(),
                'trainingSummary'      => $stats->getTrainingProgressSummary(),
                'unassignedResources'  => $stats->getUnassignedInvestorsAndProjects(),
                'statsService'         => $stats,
            ]);
        }

        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return string|Response
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'blank';

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {

            // Check role right after login
            if (!Yii::$app->user->can('super-administrator')) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash('error', 'Access denied.');
                return $this->render('login', ['model' => $model]);
            }

            $user = Yii::$app->user->identity;
            if ($user) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    $log = new \common\models\LogsAdmin([
                        'user_id' => $user->id,
                        'action_type' => \common\models\LogsAdmin::ACTION_LOGIN,
                        'section' => 'admin-panel',
                    ]);

                    if (!$log->save()) {
                        throw new \Exception('Cannot save LogsAdmin');
                    }

                    if (!$log->addDetail("User logged in: {$user->email}", \common\models\LogsAdminDetails::TYPE_TEXT)) {
                        throw new \Exception('Cannot save LogsAdminDetails');
                    }

                    $transaction->commit();
                } catch (\Throwable $e) {
                    $transaction->rollBack();
                    Yii::error('Login logging error: ' . $e->getMessage(), __METHOD__);
                }
            }
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }


}
