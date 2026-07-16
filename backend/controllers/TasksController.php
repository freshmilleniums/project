<?php

namespace backend\controllers;

use Yii;
use common\models\Tasks;
use common\models\TasksLabels;
use common\models\TasksDocuments;
use backend\models\TasksSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use backend\models\MultipleModel;
use yii\helpers\Json;
use common\services\NotificationService;

/**
 * TasksController implements the CRUD actions for Tasks model.
 */
class TasksController extends BaseController
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
     * Lists all Tasks models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new TasksSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->with('courier');

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Tasks model.
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
     * Creates a new Tasks model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Tasks();
        $model->user_id = Yii::$app->user->id;
        //$model->delivered_at = time();
        $model->delivery_date = date('Y-m-d H:i');

        $tasksLabels = [new TasksLabels()];

        if ($model->load(Yii::$app->request->post())) {
            $tasksLabels = MultipleModel::createMultiple(TasksLabels::class);
            MultipleModel::loadMultiple($tasksLabels, Yii::$app->request->post());

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $flag = $model->save();

                if ($flag) {
                    foreach ($tasksLabels as $index => $taskLabel) {
                        $taskLabel->task_id = $model->id;
                        $taskLabel->file = UploadedFile::getInstance($taskLabel, "[{$index}]file");

                        if ($taskLabel->file) {
                            if ($taskLabel->upload()) {
                                if (!$taskLabel->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                $flag = false;
                                break;
                            }
                        }
                    }
                }

                if ($flag) {
                    $transaction->commit();

                    // Check if this is the first task for the courier and send test completion notification
                    if ($model->courier_id) {
                        $totalTasks = Tasks::find()->where(['courier_id' => $model->courier_id])->count();
                        if ($totalTasks == 1) {
                            try {
                                $notificationService = new NotificationService();
                                $notificationService->sendFirstTaskNotification($model->courier_id);
                            } catch (\Exception $e) {
                                // Log error but don't interrupt the task creation process
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
            'tasksLabels' => $tasksLabels,
        ]);
    }

    /**
     * Updates an existing Tasks model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $tasksLabels = $model->labels;

        if (count($tasksLabels) == 0) {
            $tasksLabels = [new TasksLabels()];
        }

        if ($model->load(Yii::$app->request->post())) {
            $oldLabels = $model->labels;

            $tasksLabels = MultipleModel::createMultiple(TasksLabels::class, $oldLabels);
            MultipleModel::loadMultiple($tasksLabels, Yii::$app->request->post());

            $deletedLabelsIDs = array_diff(array_map(function($model) { return $model->id; }, $oldLabels), array_filter(array_map(function($model) { return $model->id; }, $tasksLabels)));

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $flag = $model->save();

                if ($flag) {
                    // Delete removed labels
                    if (!empty($deletedLabelsIDs)) {
                        TasksLabels::deleteAll(['id' => $deletedLabelsIDs]);
                    }
                }

                if ($flag) {
                    foreach ($tasksLabels as $index => $taskLabel) {
                        $taskLabel->task_id = $model->id;
                        $taskLabel->file = UploadedFile::getInstance($taskLabel, "[{$index}]file");

                        if ($taskLabel->file) {
                            if ($taskLabel->upload()) {
                                if (!$taskLabel->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                $flag = false;
                                break;
                            }
                        } else {
                            // Save without file upload if no new file
                            if (!$taskLabel->save(false)) {
                                $flag = false;
                                break;
                            }
                        }
                    }
                }

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
            'tasksLabels' => $tasksLabels,
        ]);
    }

    /**
     * Deletes an existing Tasks model.
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
     * Finds the Tasks model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Tasks the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Tasks::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionCreateForPackage()
    {
        $model = new Tasks();
        $model->user_id = Yii::$app->user->id;
        $model->created_at = time();
        $model->delivery_date = date('Y-m-d H:i');

        // Pre-fill task data from package if package_id is provided
        if (Yii::$app->request->get('package_id')) {
            $packageId = Yii::$app->request->get('package_id');
            $package = \common\models\Packages::findOne($packageId);

            if ($package) {
                $model->name = $package->name;
                $model->description = $package->description;
                $model->weight = $package->weight;
                $model->comment = $package->comment;
            }
        }

        $tasksLabels = [new TasksLabels()];

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {

            $tasksLabels = MultipleModel::createMultiple(TasksLabels::class);
            MultipleModel::loadMultiple($tasksLabels, Yii::$app->request->post());

            $transaction = Yii::$app->db->beginTransaction();
            $success = false;
            $message = '';

            try {
                $flag = $model->save();

                if ($flag) {
                    foreach ($tasksLabels as $index => $taskLabel) {
                        $taskLabel->task_id = $model->id;
                        $taskLabel->file = UploadedFile::getInstance($taskLabel, "[{$index}]file");

                        if ($taskLabel->file) {
                            if ($taskLabel->upload()) {
                                if (!$taskLabel->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                $flag = false;
                                break;
                            }
                        } elseif (!empty($taskLabel->attributes)) {
                            // Save label even without file if other attributes are filled
                            if (!$taskLabel->save(false)) {
                                $flag = false;
                                break;
                            }
                        }
                    }
                }

                if ($flag) {
                    $transaction->commit();
                    $success = true;
                    $message = 'Task created successfully';

                    // Check if this is the first task for the courier and send test completion notification
                    if ($model->courier_id) {
                        $totalTasks = Tasks::find()->where(['courier_id' => $model->courier_id])->count();
                        if ($totalTasks == 1) {
                            try {
                                $notificationService = new NotificationService();
                                $notificationService->sendFirstTaskNotification($model->courier_id);
                            } catch (\Exception $e) {
                                // Log error but don't interrupt the task creation process
                                Yii::error('Failed to send test completion notification: ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $transaction->rollBack();
                    $message = 'Failed to create task';
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
                    'tpl' => $this->renderAjax('_create_for_package_form', [
                        'model' => $model,
                        'tasksLabels' => $tasksLabels,
                    ])
                ]);
            }
        }

        // Return initial form
        return json_encode([
            'tpl' => $this->renderAjax('_create_for_package_form', [
                'model' => $model,
                'tasksLabels' => $tasksLabels,
            ])
        ]);
    }

    /**
    * Get tracking status via AJAX
    *
    * @param integer $id Task ID
    * @return string JSON response with tracking status HTML
    * @throws NotFoundHttpException if task cannot be found
    */
    public function actionGetTrackingStatus($id)
    {
        try {
            $task = $this->findModel($id);

            if (empty($task->track)) {
                return Json::encode([
                    'success' => false,
                    'message' => 'No tracking number found for this task'
                ]);
            }

            $afterShip = Yii::$app->aftership;
            // Get tracking status from AfterShip
            $trackingData = $afterShip->getTrackingStatus($task->track);

            if ($trackingData) {
                // Extract status text using the same logic as in console controller
                $statusText = $trackingData['subtag_message'] ?? $trackingData['tag'];

                // Update task fields
                $task->track_status = $statusText;
                $task->track_status_update = time();

                // Save the changes
                if (!$task->save(false)) {
                    Yii::warning('Failed to save tracking status for task ' . $task->id, __METHOD__);
                }

                return Json::encode([
                    'success' => true,
                    'tpl' => $this->renderAjax('tracking_status', [
                        'trackingData' => $trackingData,
                        'statusText' => $statusText,
                        'task' => $task
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
     * Return task to work (change status from DELIVERED to IN_PROGRESS)
     *
     * @param int $id Task ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if task cannot be found
     */
    public function actionReturnToWork($id)
    {
        $model = $this->findModel($id);

        if ($model->status != Tasks::STATUS_DELIVERED) {
            Yii::$app->session->setFlash('error', 'Task can only be returned to work from delivered status.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $model->status = Tasks::STATUS_IN_PROGRESS;
        $model->delivered_at = null; // Reset delivered timestamp

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Task has been returned to work successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Error returning task to work.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Complete task (change status from DELIVERED to COMPLETED)
     *
     * @param int $id Task ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if task cannot be found
     */
    public function actionCompleteTask($id)
    {
        $model = $this->findModel($id);

        if ($model->status != Tasks::STATUS_DELIVERED) {
            Yii::$app->session->setFlash('error', 'Task can only be completed from delivered status.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $model->status = Tasks::STATUS_COMPLETED;

        if ($model->save(false)) {
            // Check if this is the first completed task for the courier and send task completed notification
            if ($model->courier_id) {
                $totalCompletedTasks = Tasks::find()
                    ->where(['courier_id' => $model->courier_id, 'status' => Tasks::STATUS_COMPLETED])
                    ->count();
                if ($totalCompletedTasks == 1) {
                    try {
                        $notificationService = new NotificationService();
                        $notificationService->sendTaskCompletedNotification($model->courier_id);
                    } catch (\Exception $e) {
                        // Log error but don't interrupt the completion process
                        Yii::error('Failed to send task completed notification: ' . $e->getMessage());
                    }
                }
            }
            Yii::$app->session->setFlash('success', 'Task has been completed successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Error completing the task.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Download task label file
     *
     * @param int $id Label ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if label cannot be found
     */
    public function actionDownloadLabel($id)
    {
        $label = TasksLabels::findOne($id);

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
     * Download task document file
     *
     * @param int $id Document ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if document cannot be found
     */
    public function actionDownloadDocument($id)
    {
        $document = TasksDocuments::findOne($id);

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
