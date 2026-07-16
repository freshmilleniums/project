<?php

namespace backend\controllers;

use Yii;
use common\models\Packages;
use backend\models\PackagesSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use backend\models\MultipleModel;
use yii\helpers\Json;
use common\services\NotificationService;

/**
 * PackagesController implements the CRUD actions for Packages model.
 */
class PackagesController extends BaseController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Packages models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new PackagesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->with('courier');

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Packages model.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Packages model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Packages();
        $model->user_id = Yii::$app->user->id;
        //$model->delivered_at = time();

        $model->delivery_date = date('Y-m-d H:i');

        if ($model->load(Yii::$app->request->post())) {

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $flag = $model->save();

                if ($flag) {
                    $transaction->commit();
                    // Check if this is the first package for the courier and send test completion notification
                    if ($model->courier_id) {
                        $totalPackages = Packages::find()->where(['courier_id' => $model->courier_id])->count();
                        if ($totalPackages == 1) {
                            try {
                                $notificationService = new NotificationService();
                                $notificationService->sendFirstPackageNotification($model->courier_id);
                            } catch (\Exception $e) {
                                // Log error but don't interrupt the package creation process
                                Yii::error('Failed to send test completion notification: ' . $e->getMessage());
                            }
                        }
                    }

                    return $this->redirect(['index']);
                } else {
                    $transaction->rollBack();
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Packages model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $transaction = Yii::$app->db->beginTransaction();

            try {
                $flag = $model->save();

                if ($flag) {
                    $transaction->commit();
                    return $this->redirect(['index']);
                } else {
                    $transaction->rollBack();
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Packages model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Packages model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Packages the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Packages::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }


    /**
     * Package Creation for Courier via AJAX
     * @return string JSON response
     */
    public function actionCreateForCourier()
    {
        $model = new Packages();
        $model->user_id = Yii::$app->user->id;
        $model->delivery_date = date('Y-m-d H:i');
        //$model->delivered_at = time();

        // Set courier_id if passed in GET request
        if (Yii::$app->request->get('courier_id')) {
            $model->courier_id = Yii::$app->request->get('courier_id');
        }

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {

            $transaction = Yii::$app->db->beginTransaction();
            $success = false;
            $message = '';

            try {
                $flag = $model->save();

                if ($flag) {
                    $transaction->commit();
                    $success = true;
                    $message = 'Package created successfully';

                    // Check if this is the first package for the courier and send test completion notification
                    if ($model->courier_id) {
                        $totalPackages = Packages::find()->where(['courier_id' => $model->courier_id])->count();
                        if ($totalPackages == 1) {
                            try {
                                $notificationService = new NotificationService();
                                $notificationService->sendFirstPackageNotification($model->courier_id);
                            } catch (\Exception $e) {
                                // Log error but don't interrupt the package creation process
                                Yii::error('Failed to send test completion notification: ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $transaction->rollBack();
                    $message = 'Failed to create package';
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                $message = 'Error: ' . $e->getMessage();
            }

            if ($success) {
                return json_encode([
                    'success' => true,
                    'message' => $message,
                ]);
            } else {
                // Return form with errors
                return json_encode([
                    'success' => false,
                    'message' => $message,
                    'tpl' => $this->renderAjax('_create_for_courier_form', [
                        'model' => $model,
                    ])
                ]);
            }
        }

        // Return initial form
        return json_encode([
            'tpl' => $this->renderAjax('_create_for_courier_form', [
                'model' => $model,
            ])
        ]);
    }

    /**
     * Get tracking status via AJAX
     *
     * @param integer $id Package ID
     * @return string JSON response with tracking status HTML
     * @throws NotFoundHttpException if package cannot be found
     */
    public function actionGetTrackingStatus($id)
    {
        try {
            $package = $this->findModel($id);

            if (empty($package->track)) {
                return Json::encode([
                    'success' => false,
                    'message' => 'No tracking number found for this package'
                ]);
            }

            $afterShip = Yii::$app->aftership;
            // Get tracking status from AfterShip
            $trackingData = $afterShip->getTrackingStatus($package->track);

            if ($trackingData) {
                // Extract status text using the same logic as in console controller
                $statusText = $trackingData['subtag_message'] ?? $trackingData['tag'];

                // Update package fields
                $package->track_status = $statusText;
                $package->track_status_update = time();

                // Save the changes
                if (!$package->save(false)) {
                    Yii::warning('Failed to save tracking status for package ' . $package->id, __METHOD__);
                }

                return Json::encode([
                    'success' => true,
                    'tpl' => $this->renderAjax('tracking_status', [
                        'trackingData' => $trackingData,
                        'statusText' => $statusText,
                        'package' => $package
                    ])
                ]);
            } else {
                return Json::encode([
                    'success' => false,
                    'message' => 'Tracking not found in system'
                ]);
            }

        } catch (\Exception $e) {
            Yii::error('Tracking status error: ' . $e->getMessage(), __METHOD__);

            return Json::encode([
                'success' => false,
                'message' => 'Failed to get tracking status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Return package to work (change status from DELIVERED to IN_PROGRESS)
     *
     * @param int $id Package ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if package cannot be found
     */
    public function actionReturnToWork($id)
    {
        $model = $this->findModel($id);

        if ($model->status != Packages::STATUS_DELIVERED) {
            Yii::$app->session->setFlash('error', 'Package can only be returned to work from delivered status.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $model->status = Packages::STATUS_IN_PROGRESS;
        $model->delivered_at = null; // Reset delivered timestamp

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Package has been returned to work successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Error returning package to work.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Complete package (change status from DELIVERED to COMPLETED)
     *
     * @param int $id Package ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if package cannot be found
     */
    public function actionCompletePackage($id)
    {
        $model = $this->findModel($id);

        if ($model->status != Packages::STATUS_DELIVERED) {
            Yii::$app->session->setFlash('error', 'Package can only be completed from delivered status.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $model->status = Packages::STATUS_COMPLETED;

        if ($model->save(false)) {

            // Check if this is the first completed package for the courier and send task completed notification
            if ($model->courier_id) {
                $totalCompletedPackages = Packages::find()
                    ->where(['courier_id' => $model->courier_id, 'status' => Packages::STATUS_COMPLETED])
                    ->count();
                if ($totalCompletedPackages == 1) {
                    try {
                        $notificationService = new NotificationService();
                        $notificationService->sendPackageCompletedNotification($model->courier_id);
                    } catch (\Exception $e) {
                        // Log error but don't interrupt the completion process
                        Yii::error('Failed to send task completed notification: ' . $e->getMessage());
                    }
                }
            }

            Yii::$app->session->setFlash('success', 'Package has been completed successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Error completing the package.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Download package label file
     *
     * @param int $id Label ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if label cannot be found
     */
    public function actionDownloadLabel($id)
    {
        $label = PackagesLabels::findOne($id);

        if (!$label) {
            throw new NotFoundHttpException('Label not found.');
        }

        if (!file_exists($label->getFilePath())) {
            throw new NotFoundHttpException('File not found.');
        }

        return Yii::$app->response->sendFile(
            $label->getFilePath(),
            $label->getFileName(),
            ['inline' => false]
        );
    }

    /**
     * Download package document file
     *
     * @param int $id Document ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if document cannot be found
     */
    public function actionDownloadDocument($id)
    {
        $document = PackagesDocuments::findOne($id);

        if (!$document) {
            throw new NotFoundHttpException('Document not found.');
        }

        if (!file_exists($document->getFilePath())) {
            throw new NotFoundHttpException('File not found.');
        }

        return Yii::$app->response->sendFile(
            $document->getFilePath(),
            $document->getFileName(),
            ['inline' => false]
        );
    }
}