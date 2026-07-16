<?php

namespace backend\controllers;

use backend\models\User;
use backend\models\UserSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;
use common\services\UserService;
use yii\helpers\Json;
use yii\web\Response;
use common\models\UserComments;
use \backend\models\ChangePasswordForm;
use backend\models\NotificationModel;
use common\services\NotificationService;
use yii\bootstrap4\ActiveForm;

/**
 * UsersController implements the CRUD actions for User model.
 */
class UsersController extends BaseController
{

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all User models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $userService = new UserService();
        $tabsData = $userService->getTabsDataForRoles();
        $countries = \common\models\User::getCountries();

        return $this->render('index', [
            'tabsData' => $tabsData,
            'countries' => $countries,
        ]);
    }

    /**
     * Displays a single User model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        try {
            $model = $this->findModel($id);
            $countries = \common\models\User::getCountries();

            return Json::encode([
                'success' => true,
                'tpl' => $this->renderAjax('view', [
                    'model' => $model,
                    'countries' => $countries,
                ])
            ]);
        } catch (NotFoundHttpException $e) {
            return Json::encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
    }

    /**
     * Creates a new User model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate($role = null)
    {
        $model = new User();
        $password = Yii::$app->security->generateRandomString(8);
        if ($role) {
            $model->role = $role;
        }
        $administrators = User::getAdministratorsList();

        if ($this->request->isPost) {
            $model->setPassword($password);
            $model->status = User::STATUS_ACTIVE;
            $model->generateAuthKey();
            $model->generateEmailVerificationToken();
            $model->created_at = time();
            $model->updated_at = time();
            if ($model->load($this->request->post()) && $model->save()) {
                $auth = Yii::$app->authManager;
                $role = $auth->getRole($model->role);
                if ($role) {
                    $auth->assign($role, $model->id);
                }
                return $this->render('create-success', [
                    'email' => $model->email,
                    'password' => $password,
                    'model' => $model,
                ]);
            }
        } else {
            $model->loadDefaultValues();
            if ($role) {
                $model->role = $role;
            }
        }

        return $this->render('create', [
            'model' => $model,
            'administrators' => $administrators,
        ]);
    }

    public function actionUpdate($id)
    {
        try {
            $model = $this->findModel($id);
        } catch (NotFoundHttpException $e) {
            return Json::encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
        $administrators = User::getAdministratorsList();

        if ($this->request->isPost && $model->load($this->request->post())) {
            if ($model->save()) {
                $auth = Yii::$app->authManager;
                $role = $auth->getRole($model->role);
                if ($role) {
                    $auth->revokeAll($model->id);
                    $auth->assign($role, $model->id);
                }

                return Json::encode([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
            } else {
                return Json::encode([
                    'success' => false,
                    'tpl' => $this->renderAjax('_form_update_ajax', [
                        'model' => $model,
                        'administrators' => $administrators,
                    ])
                ]);
            }
        }

        return Json::encode([
            'success' => true,
            'tpl' => $this->renderAjax('_form_update_ajax', [
                'model' => $model,
                'administrators' => $administrators,
            ])
        ]);
    }

    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionAjaxValidation($id) {
        $model = $this->findModel($id);

        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Get list of employees (workers) for assignment/selection
     * @return array JSON response
     */
    public function actionGetWorkers()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $query = isset($_GET['q']) ? $_GET['q'] : '';

        $employees = User::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => 'employee'])
           // ->andWhere(['user.status' => User::STATUS_ACTIVE])
            ->andFilterWhere(['like', "CONCAT(user.first_name, ' ', user.last_name)", $query])
            ->limit(20)
            ->all();

        $results = [];
        foreach ($employees as $employee) {
            $results[] = [
                'id' => $employee->id,
                'name' => $employee->getFullName(),
                'email' => $employee->email,
            ];
        }

        return $results;
    }

    /**
     *
     */
    public function actionChangePassword($id)
    {
        if (!Yii::$app->user->can('super-administrator')) {
            Yii::$app->getResponse()->setStatusCode(403);
            throw new NotFoundHttpException();
        }

        try {
            $user = $this->findModel($id);
        } catch (NotFoundHttpException $e) {
            return Json::encode([
                'success' => false,
                'message' => 'User not found.'
            ]);
        }

        $model = new ChangePasswordForm();
        $model->setUser($user);

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->changePassword()) {
                return Json::encode([
                    'success' => true,
                    'message' => 'Password changed successfully.'
                ]);
            }

            return Json::encode([
                'success' => false,
                'tpl' => $this->renderAjax('change-password', [
                    'model' => $model,
                ])
            ]);
        }

        return Json::encode([
            'success' => true,
            'tpl' => $this->renderAjax('change-password', [
                'model' => $model,
            ])
        ]);
    }

  /*  public function actionViewContractPdf($id)
    {
        $user = User::findOne($id);

        if (!$user->contract_pdf_path || !file_exists(Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path)) {
            throw new NotFoundHttpException('Contract PDF not found.');
        }

        $filePath = Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path;

        return Yii::$app->response->sendFile($filePath, 'contract.pdf', [
            'mimeType' => 'application/pdf',
            'inline' => true
        ]);
    }*/

    /**
     * Get list of users (excluding employees) for chat
     * @return array JSON response
     */
    public function actionGetUsers()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $query = Yii::$app->request->get('q', ''); 
        $currentUser = Yii::$app->user->identity;

        if (!$currentUser) {
            return [
                'success' => false,
                'message' => 'User not authenticated'
            ];
        }

        try {
            $userQuery = User::find()
                ->alias('u')
                ->leftJoin('auth_assignment aa', 'aa.user_id = u.id')
                ->where(['u.status' => User::STATUS_ACTIVE])
                ->andWhere(['!=', 'u.id', $currentUser->id])
                ->andWhere([
                    'or',
                    ['aa.item_name' => null],
                    ['!=', 'aa.item_name', 'employee']
                ])
                ->select(['u.id', 'u.first_name', 'u.last_name', 'u.email']);

            if (!empty($query)) {
                $userQuery->andWhere([
                    'or',
                    ['like', 'u.first_name', $query],
                    ['like', 'u.last_name', $query],
                    ['like', "CONCAT(u.first_name, ' ', u.last_name)", $query]
                ]);
            }

            $users = $userQuery
                ->orderBy(['u.first_name' => SORT_ASC, 'u.last_name' => SORT_ASC])
                ->limit(30)
                ->asArray()
                ->all();

            return [
                'success' => true,
                'users' => $users
            ];

        } catch (\Exception $e) {
            Yii::error('Failed to get users list: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to load users'
            ];
        }
    }



}
