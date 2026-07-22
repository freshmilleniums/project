<?php

namespace backend\controllers;

use Yii;
use common\models\Companies;
use common\models\LogsAdmin;
use common\models\LogsAdminDetails;
use common\models\LogsCompany;
use common\models\LogsCompanyDetails;
use backend\models\CompaniesSearch;
use backend\models\User;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\web\Response;
use yii\helpers\Html;

/**
 * CompaniesController implements the CRUD actions for Companies model.
 *
 */
class CompaniesController extends BaseController
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
                    'deploy' => ['POST'],
                    'deploy-crm' => ['POST'],
                    'test-auto-deploy' => ['POST'],
                    'stop' => ['POST'],
                    'generate-api-key' => ['POST'],
                    'deployment-stream' => ['GET'],
                    'test-sse' => ['GET'],
                    'update-config' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Companies models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CompaniesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // Add eager loading for administrator relation
        $dataProvider->query->with('administrator');

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Companies model via AJAX.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        // Get deployment logs for this company
        $logsDataProvider = new \yii\data\ActiveDataProvider([
            'query' => LogsCompany::find()
                ->where(['company_id' => $model->id])
                ->with(['details', 'user'])
                ->orderBy('date DESC'),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return Json::encode([
            'tpl' => $this->renderAjax('_view', [
                'model' => $model,
                'logsDataProvider' => $logsDataProvider,
            ])
        ]);
    }

    /**
     * Creates a new Companies model.
     * If creation is successful, the browser will be redirected to the 'deployment-progress' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Companies();
        $model->scenario = 'create';

        if ($model->load(Yii::$app->request->post())) {
            $transaction = Yii::$app->db->beginTransaction();

            try {
                $model->administrator_id = null;
                if (!$model->save()) {
                    $transaction->rollBack();
                    return $this->render('create', ['model' => $model]);
                }

                $admin = new User();
                $admin->scenario = 'company_admin_create';
                $admin->first_name = $model->admin_first_name;
                $admin->last_name = $model->admin_last_name;
                $admin->email = $model->admin_email;
                $admin->setPassword($model->admin_password);
                $admin->generateAuthKey();
                $admin->generateEmailVerificationToken();
                $admin->status = User::STATUS_ACTIVE;
                $admin->company_id = $model->id;

                $admin->created_at = time();
                $admin->updated_at = time();

                if (!$admin->save()) {
                    $transaction->rollBack();
                    foreach ($admin->getErrors() as $attribute => $errors) {
                        foreach ($errors as $error) {
                            $model->addError('admin_' . $attribute, $error);
                        }
                    }
                    return $this->render('create', ['model' => $model]);
                }

                $auth = Yii::$app->authManager;
                $localAdminRole = $auth->getRole('administrator');

                if (!$localAdminRole) {
                    $transaction->rollBack();
                    $model->addError('admin_email', 'Local administrator role not found in the system.');
                    return $this->render('create', ['model' => $model]);
                }

                $auth->assign($localAdminRole, $admin->id);

                $model->administrator_id = $admin->id;
                if (!$model->save(false, ['administrator_id'])) {
                    $transaction->rollBack();
                    $model->addError('administrator_id', 'Failed to assign administrator to company.');
                    return $this->render('create', ['model' => $model]);
                }

                $transaction->commit();
                Yii::warning('Company created successfully, ID: ' . $model->id, 'company-creation');

                try {
                    $this->logCompanyCreation($model);
                } catch (\Exception $e) {
                    Yii::warning('logCompanyCreation failed: ' . $e->getMessage(), 'company-creation');
                }

                // Success message
                Yii::$app->session->setFlash('success',
                    'Company "' . Html::encode($model->name) . '" created successfully! Starting deployment...'
                );

                Yii::warning('Redirecting to deployment-progress, ID: ' . $model->id, 'company-creation');

                return $this->redirect(['deployment-progress', 'id' => $model->id]);

            } catch (\Exception $e) {
                $transaction->rollBack();
                Yii::error('Failed to create company: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString(), 'deployment');

                $model->addError('name', 'Error creating company: ' . $e->getMessage());

                return $this->render('create', ['model' => $model]);
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    /**
     * Log company creation
     */
    private function logCompanyCreation($model)
    {
        try {
            $adminLog = new LogsAdmin();
            $adminLog->user_id = Yii::$app->user->id;
            $adminLog->action_type = LogsAdmin::ACTION_COMPANY_CREATE;
            $adminLog->section = 'companies';

            $adminLog->save();

            $createdData = [
                'company_id' => $model->id,
                'company_name' => $model->name,
                'company_url' => $model->url,
                'landing_url' => $model->landing_url,
                'landing_api_key' => $model->landing_api_key,
                'administrator_id' => $model->administrator_id,
                'administrator_email' => $model->administrator->email ?? null,
                'created_from' => 'creation_form'
            ];

            $adminLog->addDetail($createdData, LogsAdminDetails::TYPE_JSON);
        } catch (\Exception $e) {
            Yii::warning('Failed to log company creation: ' . $e->getMessage(), 'company-creation');
        }
    }

    /**
     * Updates an existing Companies model via AJAX.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model->scenario = 'update';
        $oldAttributes = $model->attributes;

        // Get list of users for this company with local-administrator role using JOIN
        $localAdminsData = User::find()
            ->select(['user.id', 'user.first_name', 'user.last_name', 'user.email'])
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where([
                'user.company_id' => $model->id,
                'user.status' => User::STATUS_ACTIVE,
                'auth_assignment.item_name' => 'local-administrator'
            ])
            ->asArray()
            ->all();

        // Format data for dropdown
        $localAdmins = [];
        foreach ($localAdminsData as $admin) {
            $localAdmins[$admin['id']] = $admin['first_name'] . ' ' . $admin['last_name'] . ' (' . $admin['email'] . ')';
        }

        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            if ($model->load(Yii::$app->request->post()) && $model->save()) {
                // Log company update in admin logs
                $this->logCompanyUpdate($model, $oldAttributes);

                // Check if config update is needed (URL was changed) and auto-run it
                $configUpdateResult = null;
                if ($model->needsConfigUpdate()) {
                    $configUpdateResult = $this->executeConfigUpdate($model);
                }

                $message = 'Company updated successfully.';
                if ($configUpdateResult !== null) {
                    if ($configUpdateResult['success']) {
                        $message .= ' Configuration files updated.';
                    } else {
                        $message .= ' Warning: Configuration update failed - use "Update Config" button to retry.';
                    }
                }

                return Json::encode([
                    'success' => true,
                    'message' => $message,
                    'config_update' => $configUpdateResult,
                ]);
            } else {
                return Json::encode([
                    'success' => false,
                    'message' => 'Failed to update company.',
                    'tpl' => $this->renderAjax('_update', [
                        'model' => $model,
                        'users' => $localAdmins,
                    ])
                ]);
            }
        }

        return Json::encode([
            'tpl' => $this->renderAjax('_update', [
                'model' => $model,
                'users' => $localAdmins,
            ])
        ]);
    }

    /**
     * Deletes an existing Companies model.
     * @param int $id ID
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $model = $this->findModel($id);
        if (!$model) {
            return [
                'success' => false,
                'message' => 'Company not found'
            ];
        }

        // Log pre-deletion info in admin logs
        try {
            $adminLog = new LogsAdmin();
            $adminLog->user_id = Yii::$app->user->id;
            $adminLog->action = LogsAdmin::ACTION_COMPANY_DELETE;
            $adminLog->addDetail('company_id', $model->id);
            $adminLog->addDetail('company_name', $model->name);
            $adminLog->addDetail('company_url', $model->url);
            $adminLog->addDetail('company_landing_url', $model->landing_url);
            $adminLog->addDetail('deletion_initiated_at', date('Y-m-d H:i:s'));
            $adminLog->addDetail('deletion_method', 'complete_secure_only');
            $adminLog->save();
        } catch (\Exception $e) {
            \Yii::warning("Failed to create admin log for deletion: " . $e->getMessage());
            $adminLog = null;
        }

        $secureWrapperPath = '/usr/local/bin/crm-deploy-root';
        if (!file_exists($secureWrapperPath)) {
            return [
                'success' => false,
                'message' => 'Complete deletion unavailable - secure wrapper not found'
            ];
        }

        try {
            $result = $this->startCriticalDeletionCompany($model->id);

            if ($adminLog) {
                $adminLog->addDetail('secure_deletion_started', true);
                $adminLog->addDetail('script_path', $result['script_path']);
                $adminLog->addDetail('log_path', $result['log_path']);
                $adminLog->save();
            }

            return [
                'success' => true,
                'show_progress' => true,
                'deletion_type' => 'complete_secure',
                'message' => 'Complete company deletion started successfully',
                'warning' => 'All files, databases and configurations will be permanently deleted',
                'debug_info' => $result
            ];

        } catch (\Exception $e) {
            \Yii::error("Secure deletion failed for company {$id}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to start complete deletion: ' . $e->getMessage()
            ];
        }
    }

    /**
     *
     */
    private function startCriticalDeletionCompany($companyId)
    {
        // Validate company ID
        if (!is_numeric($companyId) || $companyId < 1 || $companyId > 10000000) {
            throw new \Exception("Invalid company ID: {$companyId}");
        }

        $wrapperPath = '/usr/local/bin/crm-deploy-root';
        if (!file_exists($wrapperPath) || !is_executable($wrapperPath)) {
            throw new \Exception("Secure wrapper not found or not executable: {$wrapperPath}");
        }

        // Create deletion script
        $scriptPath = "/tmp/critical_delete_secure_{$companyId}.sh";
        $logPath = "/tmp/critical_delete_log_{$companyId}.log";

        $scriptContent = "#!/bin/bash\n";
        $scriptContent .= "# Company {$companyId} COMPLETE deletion\n";
        $scriptContent .= "echo \"[$(date)] STARTING COMPLETE deletion of company {$companyId}\" >> {$logPath}\n";
        $scriptContent .= "echo \"[$(date)] User: " . (Yii::$app->user->identity->email ?? 'unknown') . "\" >> {$logPath}\n";
        $scriptContent .= "sudo {$wrapperPath} {$companyId} '' delete >> {$logPath} 2>&1\n";
        $scriptContent .= "EXIT_CODE=\$?\n";
        $scriptContent .= "echo \"[$(date)] Deletion completed with exit code: \$EXIT_CODE\" >> {$logPath}\n";
        $scriptContent .= "# Auto-cleanup after 1 hour\n";
        $scriptContent .= "(sleep 3600 && rm -f {$scriptPath}) &\n";

        if (file_put_contents($scriptPath, $scriptContent) === false) {
            throw new \Exception("Failed to create deletion script");
        }

        chmod($scriptPath, 0755);

        // Execute in background
        $command = "nohup bash {$scriptPath} > /dev/null 2>&1 &";
        exec($command);

        \Yii::info("Started complete deletion for company {$companyId}");

        return [
            'script_path' => $scriptPath,
            'log_path' => $logPath,
            'execution_time' => date('Y-m-d H:i:s'),
            'user_id' => Yii::$app->user->id
        ];
    }

    /**
     * Deploy company
     * Always shows modal window with real-time progress
     * @param int $id ID
     * @return string JSON response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDeployDebug($id)
    {
        $model = $this->findModel($id);

        // Check if deployment is already in progress
        if ($model->status === Companies::STATUS_DEPLOYING) {
            return Json::encode([
                'success' => false,
                'message' => 'Deployment already in progress for this company.',
            ]);
        }

        try {
            $deploymentLog = new LogsCompany();
            $deploymentLog->company_id = $model->id;
            $deploymentLog->user_id = Yii::$app->user->id;
            $deploymentLog->action_type = LogsCompany::ACTION_REDEPLOY;

            if (!$deploymentLog->save()) {
                throw new \Exception('Failed to create deployment log: ' . json_encode($deploymentLog->getErrors()));
            }

            $deploymentLog->updateProgress('Re-deployment started from web interface', 0);

            $model->status = Companies::STATUS_DEPLOYING;

            if (!$model->save(false)) {
                throw new \Exception('Failed to update company status: ' . json_encode($model->getErrors()));
            }

            // ALWAYS DEBUG MODE: Start deployment with detailed logging and return modal content
            $debugInfo = $this->startUniversalBackgroundDeploymentDebug($model, $deploymentLog->id);

            return Json::encode([
                'success' => true,
                'message' => 'Deployment started successfully.',
                'show_progress' => true,
                'debug_info' => $debugInfo,
                'progress_html' => $this->renderAjax('deployment-progress-debug', [
                    'model' => $model,
                    'debugInfo' => $debugInfo
                ])
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to start deployment: ' . $e->getMessage(), 'deployment');

            // Log error - COMPATIBLE way
            $deploymentLog->addCriticalError('Failed to start deployment: ' . $e->getMessage(), $e->getTraceAsString());

            // Reset company status
            $model->status = Companies::STATUS_STOPPED;
            $model->save(false);

            return Json::encode([
                'success' => false,
                'message' => 'Failed to start deployment: ' . $e->getMessage(),
                'debug_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Deploy CRM system specifically
     * @param int $id Company ID
     * @return array JSON response
     */
    public function actionDeployCrm($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Content-Type', 'application/json');

        $model = Companies::findOne($id);
        if (!$model) {
            return ['success' => false, 'message' => 'Company not found'];
        }

        if ($model->status === Companies::STATUS_DEPLOYING) {
            return ['success' => false, 'message' => 'CRM deployment already in progress'];
        }

        try {
            $model->status = Companies::STATUS_DEPLOYING;
            if (!$model->save(false)) {
                throw new \Exception('Failed to update company status');
            }

            $deploymentLog = new LogsCompany();
            $deploymentLog->company_id = $id;
            $deploymentLog->user_id = Yii::$app->user->id ?? 1;
            $deploymentLog->action_type = LogsCompany::ACTION_DEPLOY;

            if (!$deploymentLog->save(false)) {
                throw new \Exception('Failed to create deployment log');
            }

            $deploymentLog->addDetail([
                'step' => 'CRM deployment started from web interface',
                'progress' => 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'new_deployment' => true,
                'deployment_type' => 'crm'
            ], LogsCompanyDetails::TYPE_JSON);
           
            $this->startCRMDeploymentUltraFast($id, $deploymentLog->id);
           
            return [
                'success' => true,
                'message' => 'CRM deployment started successfully.',
                'show_progress' => true,
                'deployment_type' => 'crm',
                'new_log_id' => $deploymentLog->id,
                'debug_info' => [
                    'timestamp' => date('H:i:s'),
                    'company_id' => $id,
                    'log_id' => $deploymentLog->id,
                    'fast_start' => true
                ],
                'progress_html' => '<div class="deployment-progress">
                <h4>CRM Deployment Started</h4>
                <p>Company ID: ' . $id . '</p>
                <p>New Log ID: ' . $deploymentLog->id . '</p>
                <p>Status changed to: DEPLOYING</p>                
            </div>'
            ];

        } catch (\Exception $e) {          
            $model->status = Companies::STATUS_STOPPED;
            $model->save(false);

            return [
                'success' => false,
                'message' => 'Failed to start CRM deployment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test auto deployment
     * @param int $id Company ID
     * @return array JSON response
     */
    public function actionTestAutoDeploy($id)
    {
        // Set JSON response headers
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Content-Type', 'application/json');
      
        $model = Companies::findOne($id);
        if (!$model) {
            return ['success' => false, 'message' => 'Company not found'];
        }
       
        $this->startTestDeploymentUltraFast($id);
      
        return [
            'success' => true,
            'message' => 'Test deployment started successfully!',
            'show_progress' => true,
            'deployment_type' => 'test',
            'debug_info' => [
                'timestamp' => date('H:i:s'),
                'company_id' => $id,
                'ultra_fast' => true
            ],
            'progress_html' => '<div class="deployment-progress">
                <h4>Test Deployment Started</h4>
                <p>Company ID: ' . $id . '</p>
                <p>Creating test site...</p>
                <div class="progress">
                    <div class="progress-bar progress-bar-info" style="width: 10%">10%</div>
                </div>
            </div>'
        ];
    }

    /**
     * Stop company instance
     * @param int $id ID
     * @return string JSON response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionStop($id)
    {
        $model = $this->findModel($id);

        try {          
            $stopLog = new LogsCompany();
            $stopLog->company_id = $model->id;
            $stopLog->user_id = Yii::$app->user->id;
            $stopLog->action_type = LogsCompany::ACTION_STOP;
            $stopLog->save(false);
         
            $stopLog->addDetail([
                'step' => 'Company stop initiated from web interface',
                'timestamp' => date('Y-m-d H:i:s'),
                'user_action' => true
            ], \common\models\LogsCompanyDetails::TYPE_JSON);
          
            $nginxLog = new LogsCompany();
            $nginxLog->company_id = $model->id;
            $nginxLog->user_id = Yii::$app->user->id;
            $nginxLog->action_type = LogsCompany::ACTION_STOP;
            $nginxLog->save(false);

            $nginxLog->addDetail([
                'step' => 'Executing nginx configs disable via CompanyDeployController',
                'timestamp' => date('Y-m-d H:i:s'),
                'command' => "sudo /usr/local/bin/crm-deploy-root {$id} '' stop"
            ], \common\models\LogsCompanyDetails::TYPE_JSON);

            $command = "sudo /usr/local/bin/crm-deploy-root {$id} '' stop";
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                $nginxLog->addDetail([
                    'step' => 'Nginx configs disabled successfully',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success' => true,
                    'output' => implode("\n", $output)
                ], \common\models\LogsCompanyDetails::TYPE_JSON);
            } else {
                $nginxLog->addDetail([
                    'step' => 'Warning: Failed to disable nginx configs',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'error' => true,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ], \common\models\LogsCompanyDetails::TYPE_JSON);
            }

            // Set status to stopped
            $model->status = Companies::STATUS_STOPPED;

            if ($model->save(false)) {             
                $stopLog = new LogsCompany();
                $stopLog->company_id = $model->id;
                $stopLog->user_id = Yii::$app->user->id;
                $stopLog->action_type = LogsCompany::ACTION_STOP;
                $stopLog->save(false);
              
                $stopLog->addDetail([
                    'step' => 'Company status updated to STOPPED',
                    'status' => 'stopped',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success' => true,
                    'nginx_operation' => $returnCode === 0 ? 'success' : 'warning'
                ], \common\models\LogsCompanyDetails::TYPE_JSON);

                return Json::encode([
                    'success' => true,
                    'message' => 'Company stopped successfully.',
                ]);
            } else {              
                $errorLog = new LogsCompany();
                $errorLog->company_id = $model->id;
                $errorLog->user_id = Yii::$app->user->id;
                $errorLog->action_type = LogsCompany::ACTION_STOP;
                $errorLog->save(false);

                $errorLog->addDetail([
                    'step' => 'Failed to update company status',
                    'error' => true,
                    'message' => 'Database save failed',
                    'timestamp' => date('Y-m-d H:i:s')
                ], \common\models\LogsCompanyDetails::TYPE_JSON);

                return Json::encode([
                    'success' => false,
                    'message' => 'Failed to stop company.',
                ]);
            }

        } catch (\Exception $e) {          
            $criticalErrorLog = new LogsCompany();
            $criticalErrorLog->company_id = $model->id;
            $criticalErrorLog->user_id = Yii::$app->user->id;
            $criticalErrorLog->action_type = LogsCompany::ACTION_STOP;
            $criticalErrorLog->save(false);

            $criticalErrorLog->addDetail([
                'step' => 'Critical error during stop operation',
                'error' => true,
                'critical' => true,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], \common\models\LogsCompanyDetails::TYPE_JSON);

            return Json::encode([
                'success' => false,
                'message' => 'Error stopping company: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Start company instance
     * @param int $id ID
     * @return string JSON response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionStart($id)
    {
        $model = $this->findModel($id);

        try {          
             $startLog = new LogsCompany();
             $startLog->company_id = $model->id;
             $startLog->user_id = Yii::$app->user->id;
             $startLog->action_type = LogsCompany::ACTION_START;
             $startLog->save(false);

             $startLog->addDetail([
                'step' => 'Company start initiated from web interface',
                'timestamp' => date('Y-m-d H:i:s'),
                'user_action' => true
            ], \common\models\LogsCompanyDetails::TYPE_JSON);

            $nginxLog = new LogsCompany();
            $nginxLog->company_id = $model->id;
            $nginxLog->user_id = Yii::$app->user->id;
            $nginxLog->action_type = LogsCompany::ACTION_START;
            $nginxLog->save(false);

            $nginxLog->addDetail([
                'step' => 'Executing nginx configs enable via CompanyDeployController',
                'timestamp' => date('Y-m-d H:i:s'),
                'command' => "sudo /usr/local/bin/crm-deploy-root {$id} '' start"
            ], \common\models\LogsCompanyDetails::TYPE_JSON);

            $command = "sudo /usr/local/bin/crm-deploy-root {$id} '' start";
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                $nginxLog->addDetail([
                    'step' => 'Nginx configs enabled successfully',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success' => true,
                    'output' => implode("\n", $output)
                ], \common\models\LogsCompanyDetails::TYPE_JSON);
            } else {
                $nginxLog->addDetail([
                    'step' => 'Warning: Failed to enable nginx configs',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'error' => true,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ], \common\models\LogsCompanyDetails::TYPE_JSON);
            }

            // Set status to running
            $model->status = Companies::STATUS_RUNNING;

            if ($model->save(false)) {
                 $startLog = new LogsCompany();
                 $startLog->company_id = $model->id;
                 $startLog->user_id = Yii::$app->user->id;
                 $startLog->action_type = LogsCompany::ACTION_START;
                 $startLog->save(false);

                 $startLog->addDetail([
                    'step' => 'Company status updated to RUNNING',
                    'status' => 'running',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success' => true,
                    'nginx_operation' => $returnCode === 0 ? 'success' : 'warning'
                ], \common\models\LogsCompanyDetails::TYPE_JSON);

                return Json::encode([
                    'success' => true,
                    'message' => 'Company started successfully.',
                ]);
            } else {
                $errorLog = new LogsCompany();
                $errorLog->company_id = $model->id;
                $errorLog->user_id = Yii::$app->user->id;
                $errorLog->action_type = LogsCompany::ACTION_START;
                $errorLog->save(false);

                $errorLog->addDetail([
                    'step' => 'Failed to update company status',
                    'error' => true,
                    'message' => 'Database save failed',
                    'timestamp' => date('Y-m-d H:i:s')
                ], \common\models\LogsCompanyDetails::TYPE_JSON);

                return Json::encode([
                    'success' => false,
                    'message' => 'Failed to start company.',
                ]);
            }

        } catch (\Exception $e) {
            $criticalErrorLog = new LogsCompany();
            $criticalErrorLog->company_id = $model->id;
            $criticalErrorLog->user_id = Yii::$app->user->id;
            $criticalErrorLog->action_type = LogsCompany::ACTION_START;
            $criticalErrorLog->save(false);

            $criticalErrorLog->addDetail([
                'step' => 'Critical error during start operation',
                'error' => true,
                'critical' => true,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], \common\models\LogsCompanyDetails::TYPE_JSON);

            return Json::encode([
                'success' => false,
                'message' => 'Error starting company: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Update company configuration after domain change
     * @param int $id Company ID
     * @return string JSON response
     */
    public function actionUpdateConfig($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = $this->findModel($id);

        // Check if config update is needed
        if (!$model->needsConfigUpdate()) {
            return [
                'success' => false,
                'message' => 'No configuration update required for this company.'
            ];
        }

        // Check if already deploying
        if ($model->status === Companies::STATUS_DEPLOYING) {
            return [
                'success' => false,
                'message' => 'Company is currently deploying. Please wait.'
            ];
        }

        return $this->executeConfigUpdate($model);
    }

    /**
     * Execute config update via secure wrapper
     * Used by actionUpdateConfig and auto-update after company edit
     * @param Companies $model
     * @return array Result with success status and message
     */
    private function executeConfigUpdate($model)
    {
        try {
            // Only run if company is deployed (RUNNING or STOPPED)
            if (!in_array($model->status, [Companies::STATUS_RUNNING, Companies::STATUS_STOPPED])) {
                return [
                    'success' => false,
                    'message' => 'Company not deployed yet, skipping config update.',
                    'skipped' => true
                ];
            }

            // Execute config update synchronously via secure wrapper
            $command = "sudo /usr/local/bin/crm-deploy-root {$model->id} '' update-config " . Yii::$app->user->id;

            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                // Refresh model to verify flag was cleared
                $model->refresh();

                return [
                    'success' => true,
                    'message' => 'Configuration updated successfully.',
                ];
            } else {
                Yii::warning("Config update failed for company {$model->id}: " . implode("\n", $output), 'deployment');

                return [
                    'success' => false,
                    'message' => 'Configuration update failed. Check logs for details.',
                    'output' => implode("\n", $output)
                ];
            }

        } catch (\Exception $e) {
            Yii::error("Config update exception for company {$model->id}: " . $e->getMessage(), 'deployment');

            return [
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ];
        }
    }



    /**
     * Generate new API key for landing
     * @return string JSON response
     */
    public function actionGenerateApiKey()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $model = new Companies();
        $apiKey = $model->generateLandingApiKey();

        return [
            'success' => true,
            'apiKey' => $apiKey,
        ];
    }

    /**
     * Deployment progress page
     * @param int $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionDeploymentProgress($id)
    {
        $model = $this->findModel($id);

        // Show progress page
        return $this->render('deployment-progress', [
            'model' => $model
        ]);
    }

    /**
     * Test deployment progress page with SSE testing
     */
    public function actionTestDeploymentProgress()
    {
        return $this->render('test-sse');
    }

    /**
     * Test sse
     * URL: /companies/sse-test
     */
    public function actionSseTest()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        $this->enableCsrfValidation = false;

        Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
        Yii::$app->response->headers->set('Cache-Control', 'no-cache');
        Yii::$app->response->headers->set('Connection', 'keep-alive');

        while (ob_get_level()) {
            ob_end_clean();
        }

        Yii::$app->response->send();

        for ($i = 1; $i <= 5; $i++) {
            echo "data: " . json_encode([
                    'test' => $i,
                    'message' => "SSE Test #{$i}",
                    'time' => date('H:i:s')
                ]) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            sleep(1);
        }

        echo "data: " . json_encode(['status' => 'completed']) . "\n\n";
        flush();
        exit();
    }

    /**
     * TEST SSE connection
     */
    public function actionTestSse()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;

        // Disable CSRF validation for SSE
        $this->enableCsrfValidation = false;

        Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
        Yii::$app->response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        Yii::$app->response->headers->set('Connection', 'keep-alive');
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('X-Accel-Buffering', 'no');
        Yii::$app->response->headers->set('Pragma', 'no-cache');

        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(0);
        ignore_user_abort(false);

        Yii::$app->response->send();

        // Send test messages
        for ($i = 1; $i <= 10; $i++) {
            $testData = [
                'test_number' => $i,
                'message' => "Test message #{$i}",
                'timestamp' => date('Y-m-d H:i:s'),
                'progress' => $i * 10,
                'server_time' => time()
            ];

            echo "data: " . json_encode($testData) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            sleep(1);
        }

        echo "data: " . json_encode([
                'status' => 'completed',
                'message' => 'SSE test completed successfully',
                'total_messages' => 10
            ]) . "\n\n";

        flush();
        exit();
    }

    /**
     * SSE stream for deployment progress - with hang protection
     * @param int $id Company ID
     * @param int|null $test Test mode (1 = enable test mode)
     */
    public function actionDeploymentStream($id, $test = null)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        $this->enableCsrfValidation = false;

        Yii::$app->response->headers->set('Content-Type', 'text/event-stream');
        Yii::$app->response->headers->set('Cache-Control', 'no-cache');
        Yii::$app->response->headers->set('Connection', 'keep-alive');
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');

        while (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(60);

        Yii::$app->response->send();

        $sendSSEMessage = function($data) {
            echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        };

        if ($test == 1) {
            $sendSSEMessage([
                'status' => 'test_mode',
                'message' => 'deployment-stream test mode',
                'company_id' => $id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            for ($i = 1; $i <= 3; $i++) {
                sleep(1);
                $sendSSEMessage([
                    'test_number' => $i,
                    'message' => "Test message #$i",
                    'progress' => $i * 30,
                    'timestamp' => date('H:i:s')
                ]);
            }

            $sendSSEMessage([
                'status' => 'test_completed',
                'message' => 'Test completed successfully'
            ]);
            exit();
        }

        try {
            $company = Companies::findOne($id);
            if (!$company) {
                $sendSSEMessage([
                    'status' => 'error',
                    'error' => true,
                    'message' => "Company with ID {$id} not found",
                    'error_type' => 'not_found',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit();
            }

        } catch (\Exception $e) {
            $sendSSEMessage([
                'status' => 'error',
                'error' => true,
                'message' => 'Database error: ' . $e->getMessage(),
                'error_type' => 'database_error',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit();
        }

        if ($company->status === Companies::STATUS_RUNNING) {
            $sendSSEMessage([
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Deployment completed successfully!',
                'company_status' => 'running',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit();
        }

        $sendSSEMessage([
            'status' => 'connected',
            'message' => 'Connected to deployment stream',
            'company_id' => $id,
            'company_name' => $company->name,
            'company_status' => $company->status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        if ($company->status !== Companies::STATUS_DEPLOYING) {
            $sendSSEMessage([
                'status' => 'not_deploying',
                'message' => 'Company is not in deploying status',
                'company_status' => $company->status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit();
        }

        $lastLogId = 0;
        $lastDetailId = 0;
        $iteration = 0;
        $maxIterations = 40;
        $errorCount = 0;
        $maxErrors = 3;
        $noDataCount = 0;

        while ($iteration < $maxIterations) {
            try {
                if (connection_aborted()) {
                    Yii::info("Client disconnected from deployment stream for company {$id}", 'deployment');
                    exit();
                }

                try {
                    $currentTime = time();

                    $latestActiveLog = LogsCompany::find()
                        ->where(['company_id' => $id])
                        ->andWhere(['action_type' => [LogsCompany::ACTION_DEPLOY, LogsCompany::ACTION_REDEPLOY]])
                        ->orderBy('date DESC')
                        ->one();

                    $newLogs = [];

                    if ($latestActiveLog) {
                        $logAge = $currentTime - $latestActiveLog->date;

                        if ($logAge < 1800 && $latestActiveLog->id > $lastLogId) {
                            $newLogs = [$latestActiveLog];

                            if ($iteration === 0) {
                                $sendSSEMessage([
                                    'status' => 'tracking',
                                    'message' => 'Found deployment log #' . $latestActiveLog->id,
                                    'log_id' => $latestActiveLog->id,
                                    'log_age_minutes' => round($logAge / 60, 1),
                                    'timestamp' => date('H:i:s')
                                ]);
                            }
                            $noDataCount = 0;
                        } elseif ($iteration === 0) {
                            if ($logAge >= 1800) {
                                $sendSSEMessage([
                                    'status' => 'no_recent_logs',
                                    'message' => 'No recent deployment (last log is ' . round($logAge / 60, 1) . ' minutes old)',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                                exit();
                            }
                        }
                    } else {
                        $noDataCount++;
                        if ($iteration === 0) {
                            $sendSSEMessage([
                                'status' => 'waiting',
                                'message' => 'No deployment logs found, waiting for new deployment...',
                                'timestamp' => date('Y-m-d H:i:s')
                            ]);
                        }

                        if ($noDataCount > 10) {
                            $sendSSEMessage([
                                'status' => 'timeout_no_logs',
                                'message' => 'No deployment logs found after 10 seconds, ending stream',
                                'timestamp' => date('Y-m-d H:i:s')
                            ]);
                            exit();
                        }
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $sendSSEMessage([
                        'status' => 'warning',
                        'error' => true,
                        'message' => 'Database query error: ' . $e->getMessage(),
                        'error_count' => $errorCount,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);

                    if ($errorCount >= $maxErrors) {
                        $sendSSEMessage([
                            'status' => 'fatal_error',
                            'error' => true,
                            'message' => 'Too many database errors, stopping stream',
                            'error_count' => $errorCount
                        ]);
                        exit();
                    }

                    sleep(2);
                    $iteration++;
                    continue;
                }

                $hasNewData = false;
                foreach ($newLogs as $log) {
                    $hasNewData = true;
                    $noDataCount = 0;

                    try {
                        $details = LogsCompanyDetails::find()
                            ->where(['logs_company_id' => $log->id])
                            ->andWhere(['>', 'id', $lastDetailId])
                            ->orderBy('id ASC')
                            ->all();

                        foreach ($details as $detail) {
                            try {
                                if ($detail->data_type === LogsCompanyDetails::TYPE_JSON) {
                                    $data = json_decode($detail->data, true);
                                    if (json_last_error() === JSON_ERROR_NONE && $data) {

                                        $step = $data['step'] ?? '';
                                        $sseData = [
                                            'step' => $step,
                                            'progress' => isset($data['progress']) ? (int)$data['progress'] : null,
                                            'error' => isset($data['error']) && $data['error'],
                                            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                                            'log_id' => $log->id,
                                            'detail_id' => $detail->id,
                                            'deployment_type' => 'crm_only'
                                        ];

                                        if ($sseData['error']) {
                                            $sseData['error_message'] = $data['message'] ?? 'Unknown error';
                                            $sseData['status'] = 'deployment_error';
                                        }

                                        $sendSSEMessage($sseData);
                                    }
                                } else {
                                    $sendSSEMessage([
                                        'step' => $detail->data,
                                        'timestamp' => date('Y-m-d H:i:s'),
                                        'log_id' => $log->id,
                                        'detail_id' => $detail->id,
                                        'deployment_type' => 'crm_only'
                                    ]);
                                }

                                $lastDetailId = max($lastDetailId, $detail->id);

                            } catch (\Exception $e) {
                                $sendSSEMessage([
                                    'status' => 'warning',
                                    'error' => true,
                                    'message' => 'Error processing log detail: ' . $e->getMessage(),
                                    'log_id' => $log->id
                                ]);
                            }
                        }

                    } catch (\Exception $e) {
                        $sendSSEMessage([
                            'status' => 'warning',
                            'error' => true,
                            'message' => 'Error loading log details: ' . $e->getMessage(),
                            'log_id' => $log->id
                        ]);
                    }

                    $lastLogId = $log->id;
                }

                if (!$hasNewData) {
                    $noDataCount++;
                }

                try {
                    $company->refresh();

                    if ($company->status === Companies::STATUS_RUNNING) {
                        $sendSSEMessage([
                            'status' => 'completed',
                            'progress' => 100,
                            'message' => 'Deployment completed successfully!',
                            'company_status' => 'running',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        exit();
                    }

                    if ($company->status === Companies::STATUS_STOPPED) {
                        $sendSSEMessage([
                            'status' => 'failed',
                            'error' => true,
                            'message' => 'Deployment failed or was stopped',
                            'company_status' => 'stopped',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        exit();
                    }

                    if ($company->status !== Companies::STATUS_DEPLOYING) {
                        $sendSSEMessage([
                            'status' => 'not_deploying',
                            'message' => 'Company is no longer in deploying status',
                            'company_status' => $company->status,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        exit();
                    }

                } catch (\Exception $e) {
                    $sendSSEMessage([
                        'status' => 'warning',
                        'error' => true,
                        'message' => 'Error checking company status: ' . $e->getMessage()
                    ]);
                }

                if ($noDataCount > 15) {
                    $sendSSEMessage([
                        'status' => 'timeout_no_activity',
                        'message' => 'No deployment activity for 20 seconds, ending stream',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    exit();
                }

                // Heartbeat 10 seconds
                if ($iteration % 10 === 0 && !$hasNewData && $iteration > 0) {
                    $sendSSEMessage([
                        'heartbeat' => true,
                        'iteration' => $iteration,
                        'no_data_count' => $noDataCount,
                        'timestamp' => date('H:i:s')
                    ]);
                }

                sleep(1);
                $iteration++;

            } catch (\Exception $e) {
                $errorCount++;
                $sendSSEMessage([
                    'status' => 'critical_error',
                    'error' => true,
                    'message' => 'Unexpected stream error: ' . $e->getMessage(),
                    'error_count' => $errorCount,
                    'iteration' => $iteration,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);

                if ($errorCount >= $maxErrors) {
                    $sendSSEMessage([
                        'status' => 'fatal_error',
                        'error' => true,
                        'message' => 'Too many critical errors, terminating stream'
                    ]);
                    exit();
                }

                sleep(2);
            }
        }

        // Timeout
        $sendSSEMessage([
            'status' => 'timeout',
            'message' => 'Stream timeout after 1 minute',
            'iterations' => $iteration,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        exit();
    }

    /**
     * AJAX alternative for getting deployment logs (fallback if SSE doesn't work)
     */
    public function actionGetLiveProgress($companyId, $lastDetailId = 0)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $company = $this->findModel($companyId);

            // Find recent logs for this company
            $recentLogs = LogsCompany::find()
                ->where(['company_id' => $companyId])
                ->andWhere(['action_type' => [LogsCompany::ACTION_DEPLOY, LogsCompany::ACTION_REDEPLOY]])
                ->orderBy('date DESC')
                ->limit(5)
                ->all();

            $allNewDetails = [];
            $maxDetailId = $lastDetailId;
            $currentProgress = 0;
            $hasErrors = false;
            $isCompleted = false;

            foreach ($recentLogs as $log) {
                // Get new details since last check
                $newDetails = LogsCompanyDetails::find()
                    ->where(['logs_company_id' => $log->id])
                    ->andWhere(['>', 'id', $lastDetailId])
                    ->orderBy('id ASC')
                    ->all();

                foreach ($newDetails as $detail) {
                    $detailData = [
                        'id' => $detail->id,
                        'log_id' => $log->id,
                        'type' => $detail->data_type,
                        'timestamp' => date('Y-m-d H:i:s', $log->date)
                    ];

                    if ($detail->data_type === LogsCompanyDetails::TYPE_JSON) {
                        $data = json_decode($detail->data, true);
                        if ($data) {
                            $detailData = array_merge($detailData, [
                                'step' => $data['step'] ?? 'Processing...',
                                'progress' => $data['progress'] ?? null,
                                'error' => isset($data['error']) && $data['error'],
                                'message' => $data['message'] ?? '',
                                'status' => $data['status'] ?? null
                            ]);

                            if ($detailData['error']) {
                                $hasErrors = true;
                            }

                            if (isset($data['progress']) && is_numeric($data['progress'])) {
                                $currentProgress = max($currentProgress, (int)$data['progress']);
                            }

                            if (isset($data['status']) && $data['status'] === 'completed') {
                                $isCompleted = true;
                            }
                        }
                    } else {
                        $detailData['step'] = $detail->data;
                    }

                    $allNewDetails[] = $detailData;
                    $maxDetailId = max($maxDetailId, $detail->id);
                }
            }

            // Check company status
            $company->refresh();

            return [
                'success' => true,
                'company_status' => $company->getStatusName(),
                'deployment_status' => [
                    'is_running' => $company->status === Companies::STATUS_DEPLOYING,
                    'is_completed' => $company->status === Companies::STATUS_RUNNING || $isCompleted,
                    'is_failed' => $company->status === Companies::STATUS_STOPPED && $hasErrors,
                    'current_progress' => $currentProgress,
                    'has_errors' => $hasErrors
                ],
                'new_details' => $allNewDetails,
                'last_detail_id' => $maxDetailId,
                'has_more' => count($allNewDetails) > 0,
                'check_time' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            Yii::error('Error getting live progress: ' . $e->getMessage(), 'deployment');
            return [
                'success' => false,
                'message' => 'Error loading progress: ' . $e->getMessage()
            ];
        }
    }

    /**
     *  Detailed view of specific deployment log
     */
    public function actionViewDeploymentLog($logId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $log = LogsCompany::find()
                ->where(['id' => $logId])
                ->with(['details', 'company', 'user'])
                ->one();

            if (!$log) {
                return ['success' => false, 'message' => 'Log not found'];
            }

            $logData = [
                'id' => $log->id,
                'company_name' => $log->company ? $log->company->name : 'Unknown',
                'action_type' => $log->action_type,
                'date' => date('Y-m-d H:i:s', $log->date),
                'user_name' => $log->user ? ($log->user->first_name . ' ' . $log->user->last_name) : 'System',
                'details' => []
            ];

            $currentProgress = 0;
            $hasErrors = false;
            $lastError = null;

            foreach ($log->details as $detail) {
                $detailData = [
                    'id' => $detail->id,
                    'type' => $detail->data_type,
                    'raw_data' => $detail->data
                ];

                if ($detail->data_type === LogsCompanyDetails::TYPE_JSON) {
                    $data = json_decode($detail->data, true);
                    if ($data) {
                        $detailData['parsed'] = $data;
                        $detailData['step'] = $data['step'] ?? '';
                        $detailData['progress'] = $data['progress'] ?? null;
                        $detailData['timestamp'] = $data['timestamp'] ?? date('Y-m-d H:i:s', $log->date);
                        $detailData['is_error'] = isset($data['error']) && $data['error'];

                        if ($detailData['is_error']) {
                            $hasErrors = true;
                            $lastError = $data['message'] ?? 'Unknown error';
                        }

                        if (isset($data['progress']) && is_numeric($data['progress'])) {
                            $currentProgress = max($currentProgress, (int)$data['progress']);
                        }
                    }
                } else {
                    $detailData['text'] = $detail->data;
                }

                $logData['details'][] = $detailData;
            }

            $logData['summary'] = [
                'current_progress' => $currentProgress,
                'has_errors' => $hasErrors,
                'last_error' => $lastError,
                'total_steps' => count($log->details),
                'is_completed' => $currentProgress >= 100,
                'is_failed' => $hasErrors && $currentProgress < 100
            ];

            return [
                'success' => true,
                'log' => $logData
            ];

        } catch (\Exception $e) {
            Yii::error('Error viewing deployment log: ' . $e->getMessage(), 'deployment');
            return [
                'success' => false,
                'message' => 'Error loading log: ' . $e->getMessage()
            ];
        }
    }

    /**
     *  API for getting logs in real-time (alternative to SSE)
     */
    public function actionGetDeploymentProgress($companyId, $lastDetailId = 0)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $company = $this->findModel($companyId);

            // Find active deployment log
            $activeLog = LogsCompany::find()
                ->where(['company_id' => $companyId])
                ->andWhere(['action_type' => [LogsCompany::ACTION_DEPLOY, LogsCompany::ACTION_REDEPLOY]])
                ->orderBy('date DESC')
                ->one();

            if (!$activeLog) {
                return [
                    'success' => false,
                    'message' => 'No active deployment log found'
                ];
            }

            // Get new details since last request
            $newDetails = LogsCompanyDetails::find()
                ->where(['logs_company_id' => $activeLog->id])
                ->andWhere(['>', 'id', $lastDetailId])
                ->orderBy('id ASC')
                ->all();

            $details = [];
            $lastDetailIdFound = $lastDetailId;
            $currentProgress = 0;
            $hasErrors = false;
            $isCompleted = false;

            foreach ($newDetails as $detail) {
                $detailData = [
                    'id' => $detail->id,
                    'type' => $detail->data_type,
                ];

                if ($detail->data_type === LogsCompanyDetails::TYPE_JSON) {
                    $data = json_decode($detail->data, true);
                    if ($data) {
                        $detailData = array_merge($detailData, [
                            'step' => $data['step'] ?? '',
                            'progress' => $data['progress'] ?? null,
                            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                            'error' => isset($data['error']) && $data['error'],
                            'message' => $data['message'] ?? '',
                            'status' => $data['status'] ?? null
                        ]);

                        if ($detailData['error']) {
                            $hasErrors = true;
                        }

                        if (isset($data['progress']) && is_numeric($data['progress'])) {
                            $currentProgress = max($currentProgress, (int)$data['progress']);
                        }

                        if (isset($data['status']) && $data['status'] === 'completed') {
                            $isCompleted = true;
                        }
                    }
                }

                $details[] = $detailData;
                $lastDetailIdFound = $detail->id;
            }

            // Check company status
            $company->refresh();

            return [
                'success' => true,
                'company_status' => $company->getStatusName(),
                'deployment_status' => [
                    'is_running' => $company->status === Companies::STATUS_DEPLOYING,
                    'is_completed' => $company->status === Companies::STATUS_RUNNING || $isCompleted,
                    'is_failed' => $company->status === Companies::STATUS_STOPPED && $hasErrors,
                    'current_progress' => $currentProgress,
                    'has_errors' => $hasErrors
                ],
                'new_details' => $details,
                'last_detail_id' => $lastDetailIdFound,
                'has_more' => count($newDetails) > 0
            ];

        } catch (\Exception $e) {
            Yii::error('Error getting deployment progress: ' . $e->getMessage(), 'deployment');
            return [
                'success' => false,
                'message' => 'Error loading progress: ' . $e->getMessage()
            ];
        }
    }

    /**
     *  Get detailed deployment error information
     */
    public function actionGetDeploymentError($logId, $detailId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $detail = LogsCompanyDetails::find()
                ->where(['id' => $detailId, 'logs_company_id' => $logId])
                ->one();

            if (!$detail) {
                return [
                    'success' => false,
                    'message' => 'Log detail not found'
                ];
            }

            $errorData = [
                'id' => $detail->id,
                'log_id' => $logId,
                'data_type' => $detail->data_type,
                'raw_data' => $detail->data,
                'parsed_data' => null,
                'error_analysis' => []
            ];

            if ($detail->data_type === LogsCompanyDetails::TYPE_JSON) {
                $parsed = json_decode($detail->data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $errorData['parsed_data'] = $parsed;

                    if (isset($parsed['error']) && $parsed['error']) {
                        $errorData['error_analysis'] = [
                            'error_message' => $parsed['message'] ?? 'Unknown error',
                            'step' => $parsed['step'] ?? '',
                            'timestamp' => $parsed['timestamp'] ?? '',
                            'progress' => $parsed['progress'] ?? null,
                            'critical' => $parsed['critical'] ?? false
                        ];

                        $additionalFields = [
                            'error_details', 'stack_trace', 'error_file', 'error_line',
                            'missing_components', 'failed_checks', 'system_info',
                            'command_output', 'exit_code', 'stderr'
                        ];

                        foreach ($additionalFields as $field) {
                            if (isset($parsed[$field])) {
                                $errorData['error_analysis'][$field] = $parsed[$field];
                            }
                        }
                    }
                }
            }

            return [
                'success' => true,
                'error_details' => $errorData
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error retrieving deployment error: ' . $e->getMessage()
            ];
        }
    }

    /**
     *  Debug page for logs
     */
    public function actionDebugLogs()
    {
        return $this->render('debug-logs');
    }

    /**
     * Get company logs for debugging
     */
    public function actionDebugCompanyLogs()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $logs = LogsCompany::find()
                ->with(['company', 'user'])
                ->orderBy('date DESC')
                ->limit(20)
                ->all();

            $logData = [];
            foreach ($logs as $log) {
                $detailsCount = LogsCompanyDetails::find()
                    ->where(['logs_company_id' => $log->id])
                    ->count();

                $logData[] = [
                    'id' => $log->id,
                    'company_id' => $log->company_id,
                    'company_name' => $log->company ? $log->company->name : null,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user ? ($log->user->first_name . ' ' . $log->user->last_name) : null,
                    'action_type' => $log->action_type,
                    'date' => $log->date,
                    'details_count' => $detailsCount
                ];
            }

            return [
                'success' => true,
                'logs' => $logData,
                'total_logs' => count($logData)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     *  Get company log details for debugging
     */
    public function actionDebugCompanyLogDetails()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $details = LogsCompanyDetails::find()
                ->orderBy('id DESC')
                ->limit(30)
                ->all();

            $detailData = [];
            foreach ($details as $detail) {
                $detailData[] = [
                    'id' => $detail->id,
                    'logs_company_id' => $detail->logs_company_id,
                    'data_type' => $detail->data_type,
                    'data' => $detail->data
                ];
            }

            return [
                'success' => true,
                'details' => $detailData,
                'total_details' => count($detailData)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     *  Prepare CRM config files from templates
     */
    public function actionPrepareCrmConfig($companyId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $company = Companies::findOne($companyId);
            if (!$company) {
                return ['success' => false, 'message' => 'Company not found'];
            }

            $crmBasePath = null;
            $possiblePaths = [
                Yii::getAlias('@app') . '/../deploy-storage/crm-base',
                dirname(Yii::getAlias('@app')) . '/deploy-storage/crm-base'
            ];

            foreach ($possiblePaths as $path) {
                if (is_dir($path)) {
                    $crmBasePath = realpath($path);
                    break;
                }
            }

            if (!$crmBasePath) {
                return [
                    'success' => false,
                    'message' => 'CRM base files not found in deploy-storage/crm-base'
                ];
            }

            $configResults = [];

            $mainTemplate = $crmBasePath . '/common/config/main-local.php.template';
            $mainTarget = $crmBasePath . '/common/config/main-local.php';

            if (file_exists($mainTemplate)) {
                $templateContent = file_get_contents($mainTemplate);

                $replacements = [
                    '{{DB_HOST}}' => 'localhost',
                    '{{DB_NAME}}' => 'crm_delivery',
                    '{{DB_USER}}' => 'crm_delivery_usr',
                    '{{DB_PASSWORD}}' => $this->getDatabasePassword(),
                    '{{WEBSOCKET_SERVER_URL}}' => 'http://127.0.0.1:8901',
                ];

                $processedContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

                $writeResult = file_put_contents($mainTarget, $processedContent);
                $configResults['main_local'] = [
                    'template' => $mainTemplate,
                    'target' => $mainTarget,
                    'success' => $writeResult !== false,
                    'size' => $writeResult ?: 0,
                    'replacements_applied' => count($replacements)
                ];
            } else {
                $configResults['main_local'] = [
                    'error' => 'Template not found: ' . $mainTemplate
                ];
            }

            $paramsTemplate = $crmBasePath . '/common/config/params-local.php.template';
            $paramsTarget = $crmBasePath . '/common/config/params-local.php';

            if (file_exists($paramsTemplate)) {
                $templateContent = file_get_contents($paramsTemplate);

                $domain = parse_url($company->url ?: 'localhost', PHP_URL_HOST) ?: 'localhost';
                $companyDomain = "company-{$companyId}.crm-delivery.site";

                $replacements = [
                    '{{COMPANY_ID}}' => $companyId,
                    '{{AFTERSHIP_API_KEY}}' => 'asat_d28497cd46d840ee9d1827f5db9079ee',
                    '{{ADMIN_EMAIL}}' => "admin@{$domain}",
                    '{{SUPPORT_EMAIL}}' => "support@{$domain}",
                    '{{SENDER_EMAIL}}' => "noreply@{$domain}",
                    '{{SENDER_NAME}}' => $company->name,
                    '{{WEBSOCKET_SECRET}}' => $this->generateSecretKey(),
                    '{{WEBSOCKET_CLIENT_URL}}' => "wss://{$companyDomain}/websocket",
                    '{{SSL_ENABLED}}' => 'false',
                    '{{SSL_CERT_PATH}}' => '/var/www/httpd-cert/cert.crt',
                    '{{SSL_KEY_PATH}}' => '/var/www/httpd-cert/cert.key',
                    '{{DEPLOYMENT_DATE}}' => date('Y-m-d H:i:s'),
                    '{{COMPANY_DOMAIN}}' => $companyDomain,
                    '{{COMPANY_NAME}}' => addslashes($company->name),
                    '{{FRONTEND_URL}}' => "http://{$companyDomain}",
                    '{{BACKEND_URL}}' => "http://{$companyDomain}/crm-panel",
                ];

                $processedContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

                $writeResult = file_put_contents($paramsTarget, $processedContent);
                $configResults['params_local'] = [
                    'template' => $paramsTemplate,
                    'target' => $paramsTarget,
                    'success' => $writeResult !== false,
                    'size' => $writeResult ?: 0,
                    'replacements_applied' => count($replacements)
                ];
            } else {
                $configResults['params_local'] = [
                    'error' => 'Template not found: ' . $paramsTemplate
                ];
            }

            if (isset($configResults['main_local']['target'])) {
                chmod($configResults['main_local']['target'], 0644);
            }
            if (isset($configResults['params_local']['target'])) {
                chmod($configResults['params_local']['target'], 0644);
            }

            $allSuccess = true;
            foreach ($configResults as $result) {
                if (isset($result['success']) && !$result['success']) {
                    $allSuccess = false;
                    break;
                }
                if (isset($result['error'])) {
                    $allSuccess = false;
                    break;
                }
            }

            return [
                'success' => $allSuccess,
                'company_id' => $companyId,
                'crm_base_path' => $crmBasePath,
                'config_results' => $configResults,
                'message' => $allSuccess ?
                    'CRM configuration files prepared successfully' :
                    'Some configuration files failed to prepare'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error preparing CRM config: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get database password from current connection
     */
    private function getDatabasePassword()
    {
        try {
            $dsn = Yii::$app->db->dsn;
            $username = Yii::$app->db->username;
            $password = Yii::$app->db->password;
            return $password;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate secret key for CRM
     */
    private function generateSecretKey($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     *  Check CRM prerequisites before deployment
     */
    public function actionCheckCrmPrerequisites($companyId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $company = Companies::findOne($companyId);
            if (!$company) {
                return ['success' => false, 'message' => 'Company not found'];
            }

            $checks = [];
            $missingComponents = [];
            $allChecksPass = true;

            $crmPaths = [
                Yii::getAlias('@app') . '/../deploy-storage/crm-base',
                Yii::getAlias('@app') . '/../../deploy-storage/crm-base',
                dirname(Yii::getAlias('@app')) . '/deploy-storage/crm-base',
                Yii::getAlias('@app') . '/crm-files'
            ];

            $crmSourceFound = false;
            $crmSourcePath = '';
            $crmSourceDetails = [];

            foreach ($crmPaths as $path) {
                $realPath = realpath($path);
                $exists = $realPath && is_dir($realPath);

                $crmSourceDetails[$path] = [
                    'exists' => $exists,
                    'real_path' => $realPath ?: 'not found',
                    'readable' => $exists ? is_readable($realPath) : false
                ];

                if ($exists) {
                    $crmSourceFound = true;
                    $crmSourcePath = $realPath;

                    $requiredFiles = [
                        'common/config/main-local.php.template',
                        'common/config/params-local.php.template'
                    ];

                    $structureCheck = [];
                    foreach ($requiredFiles as $file) {
                        $filePath = $crmSourcePath . '/' . $file;
                        $structureCheck[$file] = file_exists($filePath);
                    }

                    $crmSourceDetails[$path]['structure_check'] = $structureCheck;
                    $crmSourceDetails[$path]['has_config_templates'] =
                        $structureCheck['common/config/main-local.php.template'] &&
                        $structureCheck['common/config/params-local.php.template'];

                    break;
                }
            }

            $checks['crm_source'] = [
                'name' => 'CRM Source Files',
                'status' => $crmSourceFound,
                'path' => $crmSourcePath,
                'details' => $crmSourceDetails,
                'message' => $crmSourceFound ?
                    "CRM source files found at: {$crmSourcePath}" :
                    'CRM source files not found in any of the expected locations'
            ];

            if (!$crmSourceFound) {
                $missingComponents[] = 'CRM source files in deploy-storage/crm-base';
                $allChecksPass = false;
            }

            $phpExtensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'zip'];
            $missingExtensions = [];
            foreach ($phpExtensions as $ext) {
                if (!extension_loaded($ext)) {
                    $missingExtensions[] = $ext;
                }
            }

            $checks['php_extensions'] = [
                'name' => 'PHP Extensions',
                'status' => empty($missingExtensions),
                'required' => $phpExtensions,
                'missing' => $missingExtensions,
                'message' => empty($missingExtensions) ? 'All required PHP extensions loaded' : 'Missing PHP extensions: ' . implode(', ', $missingExtensions)
            ];

            if (!empty($missingExtensions)) {
                $missingComponents[] = 'PHP extensions: ' . implode(', ', $missingExtensions);
                $allChecksPass = false;
            }

            $writablePaths = [
                '/tmp',
                '/var/log',
                dirname(Yii::getAlias('@app'))
            ];

            $permissionIssues = [];
            foreach ($writablePaths as $path) {
                if (!is_writable($path)) {
                    $permissionIssues[] = $path;
                }
            }

            $checks['permissions'] = [
                'name' => 'File Permissions',
                'status' => empty($permissionIssues),
                'checked_paths' => $writablePaths,
                'non_writable' => $permissionIssues,
                'message' => empty($permissionIssues) ? 'All paths writable' : 'Non-writable paths: ' . implode(', ', $permissionIssues)
            ];

            if (!empty($permissionIssues)) {
                $missingComponents[] = 'Writable permissions for: ' . implode(', ', $permissionIssues);
                $allChecksPass = false;
            }

            $dbChecks = [];

            $dbConnectionOk = false;
            $crmTablesExist = false;

            try {
                $connection = Yii::$app->db;
                $connection->open();
                $dbConnectionOk = true;
                $dbChecks['yii_connection'] = true;

                $dbName = $connection->createCommand("SELECT DATABASE()")->queryScalar();
                $dbChecks['database_name'] = $dbName;

            } catch (\Exception $e) {
                $dbChecks['connection_error'] = $e->getMessage();
                $dbConnectionOk = false;
            }

            if ($dbConnectionOk && !$crmTablesExist) {
                try {
                    $testTableName = 'crm_test_' . time();
                    $connection->createCommand("
                        CREATE TABLE `{$testTableName}` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `test` varchar(50),
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB
                    ")->execute();

                    $connection->createCommand("DROP TABLE `{$testTableName}`")->execute();

                    $dbChecks['can_create_tables'] = true;
                } catch (\Exception $e) {
                    $dbChecks['can_create_tables'] = false;
                    $dbChecks['create_table_error'] = $e->getMessage();
                }
            } else {
                $dbChecks['can_create_tables'] = $crmTablesExist;
            }

            $checks['database_config'] = [
                'name' => 'Database Configuration',
                'status' => $dbConnectionOk,
                'details' => $dbChecks,
                'message' => $dbConnectionOk ?
                    'Database connection OK' . ($crmTablesExist ? ' (CRM tables exist)' : ' (CRM tables need setup)') :
                    'Database connection failed'
            ];

            if (!$dbConnectionOk) {
                $missingComponents[] = 'Database connection';
                $allChecksPass = false;
            } elseif (!$dbChecks['can_create_tables'] && !$crmTablesExist) {
                $missingComponents[] = 'Database table creation permissions';
                $allChecksPass = false;
            }

            $requiredCommands = ['mysql', 'mysqldump', 'wget', 'curl', 'unzip'];
            $missingCommands = [];

            foreach ($requiredCommands as $cmd) {
                $output = [];
                $returnCode = null;
                exec("which {$cmd} 2>/dev/null", $output, $returnCode);
                if ($returnCode !== 0) {
                    $missingCommands[] = $cmd;
                }
            }

            $checks['system_commands'] = [
                'name' => 'System Commands',
                'status' => empty($missingCommands),
                'required' => $requiredCommands,
                'missing' => $missingCommands,
                'message' => empty($missingCommands) ? 'All required commands available' : 'Missing commands: ' . implode(', ', $missingCommands)
            ];

            if (!empty($missingCommands)) {
                $missingComponents[] = 'System commands: ' . implode(', ', $missingCommands);
                $allChecksPass = false;
            }

            return [
                'success' => true,
                'all_checks_pass' => $allChecksPass,
                'checks' => $checks,
                'missing_components' => $missingComponents,
                'recommendations' => $this->getCrmRecommendations($missingComponents)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error checking CRM prerequisites: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get recommendations for fixing CRM prerequisites
     */
    private function getCrmRecommendations($missingComponents)
    {
        $recommendations = [];

        foreach ($missingComponents as $component) {
            if (stripos($component, 'CRM source') !== false) {
                $recommendations[] = [
                    'issue' => 'CRM source files not found',
                    'solution' => 'Clone CRM repository to deploy-storage/crm-base: git clone https://gitlab.postgen.xyz/delivery-crm/delivery-crm.git deploy-storage/crm-base',
                    'expected_path' => '/var/www/crm_delivery_usr/data/www/admin.crm-delivery.site/deploy-storage/crm-base',
                    'required_files' => [
                        'common/config/main-local.php.template',
                        'common/config/params-local.php.template'
                    ],
                    'priority' => 'high'
                ];
            } elseif (stripos($component, 'PHP extensions') !== false) {
                $recommendations[] = [
                    'issue' => 'Missing PHP extensions',
                    'solution' => 'Install missing PHP extensions via FastPanel: Settings → Applications → Search for php extensions',
                    'priority' => 'medium'
                ];
            } elseif (stripos($component, 'permissions') !== false) {
                $recommendations[] = [
                    'issue' => 'File permission issues',
                    'solution' => 'Fix file permissions: chown -R crm_delivery_usr:crm_delivery_usr /var/www/crm_delivery_usr && chmod -R 755 /var/www/crm_delivery_usr',
                    'priority' => 'medium'
                ];
            } elseif (stripos($component, 'Database') !== false) {
                $recommendations[] = [
                    'issue' => 'Database configuration missing',
                    'solution' => 'Database connection should work automatically (same as admin panel). Check if CRM tables need to be created.',
                    'priority' => 'low'
                ];
            } elseif (stripos($component, 'commands') !== false) {
                $recommendations[] = [
                    'issue' => 'Missing system commands',
                    'solution' => 'Install missing packages: apt-get update && apt-get install mysql-client wget curl unzip',
                    'priority' => 'low'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Launch CRM deployment without any blocking operations
     */
    /**
     * Launch CRM deployment without any blocking operations
     */
    private function startCRMDeploymentUltraFast($companyId, $logId)
    {
        try {
            if (!$logId) {
                throw new \Exception("logId is required for CRM deployment");
            }

            if (!is_numeric($companyId) || $companyId < 1 || $companyId > 10000000) {
                throw new \Exception("Invalid company ID: {$companyId}");
            }

            $secureWrapper = '/usr/local/bin/crm-deploy-root';

            if (!file_exists($secureWrapper)) {
                throw new \Exception("Secure wrapper not found: {$secureWrapper}. Run setup_crm_deploy_custom.sh first!");
            }

            $wrapperCommand = "{$secureWrapper} {$companyId} {$logId}";

            $scriptContent = "#!/bin/bash
                set -e
                
                echo \"=== SECURE CRM Deployment Script Started ===\"
                echo \"Date: \$(date)\"
                echo \"Company ID: {$companyId}\"
                echo \"Log ID: {$logId}\"
                echo \"Deploy Type: CRM Only\"
                echo \"User: \$(whoami)\"
                echo
                
                echo \"Testing sudo access...\"
                sudo -n -l {$secureWrapper} > /dev/null 2>&1 || {
                    echo \"ERROR: sudo without password is not configured for wrapper\"
                    exit 1
                }
                
                echo \"Starting CRM deployment...\"
                echo \"Running: sudo {$wrapperCommand}\"
                
                exec sudo {$wrapperCommand} 2>&1
                ";

            $scriptPath = "/tmp/crm_deploy_secure_{$companyId}_{$logId}.sh";
            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            Yii::info("Created SECURE CRM deployment script: {$scriptPath}", 'deployment');

            $command = "nohup bash {$scriptPath} >> /tmp/crm_deploy_log_{$companyId}.log 2>&1 &";
            exec($command, $output, $returnCode);

            Yii::info("Started SECURE CRM deployment with command: {$command}, return code: {$returnCode}", 'deployment');

            exec("(sleep 600 && rm -f {$scriptPath}) > /dev/null 2>&1 &");

            return true;

        } catch (\Exception $e) {
            Yii::error("Secure CRM deployment start error: " . $e->getMessage(), 'deployment');
            return false;
        }
    }


    /**
     * Launch test deployment
     */
    private function startTestDeploymentUltraFast($companyId)
    {
        try {
            $phpPath = $this->findUniversalPHPPath();
            $consolePath = Yii::getAlias('@app') . "/../yii";

            $scriptContent = "#!/bin/bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
cd " . dirname($consolePath) . "
{$phpPath} {$consolePath} company-deploy/bootstrap-test {$companyId} >> /tmp/test_ultra_fast_{$companyId}.log 2>&1 &
";

            $scriptPath = "/tmp/test_ultra_fast_{$companyId}.sh";
            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            exec("nohup bash {$scriptPath} > /dev/null 2>&1 &");
            exec("(sleep 300 && rm -f {$scriptPath}) > /dev/null 2>&1 &");

        } catch (\Exception $e) {
            error_log("Ultra fast test start error: " . $e->getMessage());
        }
    }

    /**
     * Debug version of background deployment startup with detailed logging
     */
    private function startUniversalBackgroundDeploymentDebug($company, $logId)
    {
        $debugInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'company_id' => $company->id,
            'log_id' => $logId
        ];

        try {
            // Find PHP path
            $phpPath = $this->findUniversalPHPPath();
            $debugInfo['php_path'] = $phpPath;

            // Test PHP path
            $output = [];
            $returnCode = null;
            exec("{$phpPath} -v 2>&1", $output, $returnCode);
            $debugInfo['php_test'] = [
                'command' => "{$phpPath} -v",
                'return_code' => $returnCode,
                'output' => implode('\n', $output)
            ];

            // Get console path
            $consolePath = Yii::getAlias('@app') . "/../yii";
            $debugInfo['console_path'] = $consolePath;
            $debugInfo['console_exists'] = file_exists($consolePath);

            if (file_exists($consolePath)) {
                $debugInfo['console_permissions'] = substr(sprintf('%o', fileperms($consolePath)), -4);
            }

            // Test console command
            $testCommand = "{$phpPath} {$consolePath} help";
            exec("{$testCommand} 2>&1", $testOutput, $testReturnCode);
            $debugInfo['console_test'] = [
                'command' => $testCommand,
                'return_code' => $testReturnCode,
                'output' => implode('\n', array_slice($testOutput, 0, 10)) // First 10 lines
            ];

            // Create log path
            $logPath = "/tmp/deployment_{$company->id}_{$logId}.log";
            $debugInfo['log_path'] = $logPath;

            // Test log path permissions
            $logDir = dirname($logPath);
            $debugInfo['log_dir_writable'] = is_writable($logDir);

            // Create deployment script with DEBUG info
            $scriptContent = "#!/bin/bash
# DEBUG deployment script
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/php/*/bin

echo \"=== DEBUG DEPLOYMENT STARTED ===\" >> {$logPath}
echo \"Date: \$(date)\" >> {$logPath}
echo \"PHP Path: {$phpPath}\" >> {$logPath}
echo \"Console Path: {$consolePath}\" >> {$logPath}
echo \"Working Dir: \$(pwd)\" >> {$logPath}
echo \"User: \$(whoami)\" >> {$logPath}
echo \"PATH: \$PATH\" >> {$logPath}

# Change to app directory
cd " . dirname($consolePath) . "
echo \"Changed to: \$(pwd)\" >> {$logPath}

# Test company/deploy command exists
echo \"Testing command exists...\" >> {$logPath}
{$phpPath} {$consolePath} help company 2>&1 >> {$logPath}

# Execute deployment
echo \"Starting actual deployment...\" >> {$logPath}
{$phpPath} -d memory_limit=1024M -d max_execution_time=0 {$consolePath} company/deploy {$company->id} {$logId} >> {$logPath} 2>&1

echo \"=== DEPLOYMENT FINISHED ===\" >> {$logPath}
echo \"Exit code: \$?\" >> {$logPath}
";

            $scriptPath = "/tmp/deploy_debug_{$company->id}_{$logId}.sh";
            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            $debugInfo['script_path'] = $scriptPath;
            $debugInfo['script_created'] = file_exists($scriptPath);

            // Execute background process
            $command = "nohup bash {$scriptPath} &";
            $debugInfo['exec_command'] = $command;

            exec($command, $execOutput, $execReturnCode);

            $debugInfo['exec_result'] = [
                'return_code' => $execReturnCode,
                'output' => $execOutput
            ];

            // Give process time to start and check initial log
            sleep(2);

            if (file_exists($logPath)) {
                $debugInfo['initial_log'] = file_get_contents($logPath);
            } else {
                $debugInfo['log_file_missing'] = true;
            }

            // Log debug info to deployment log
            $log = LogsCompany::findOne($logId);
            if ($log) {
                $log->updateProgress('DEBUG: Deployment process started', 1, [
                    'debug_info' => $debugInfo
                ]);
            }

        } catch (\Exception $e) {
            $debugInfo['error'] = $e->getMessage();
            $debugInfo['trace'] = $e->getTraceAsString();
        }

        return $debugInfo;
    }

    /**
     * Find PHP CLI path for ANY environment (universal)
     */
    private function findUniversalPHPPath()
    {
        // Comprehensive list for all major hosting environments
        $possiblePaths = [
            // Ubuntu/Debian standard
            '/usr/bin/php8.1',
            '/usr/bin/php8.2',
            '/usr/bin/php8.0',
            '/usr/bin/php',

            // CentOS/RedHat
            '/bin/php',
            '/usr/local/bin/php',

            // FASTPANEL paths
            '/opt/php/8.1/bin/php',
            '/opt/php/8.2/bin/php',

            // cPanel paths
            '/opt/cpanel/ea-php81/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',

            // Plesk paths
            '/opt/plesk/php/8.1/bin/php',
            '/opt/plesk/php/8.2/bin/php',

            // DirectAdmin paths
            '/usr/local/php81/bin/php',
            '/usr/local/php82/bin/php',

            // Generic alternatives
            '/usr/local/bin/php8.1',
            '/usr/local/bin/php8.2',

            // System PATH fallback
            'php'
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'php' || file_exists($path)) {
                $output = [];
                $returnCode = null;
                exec("{$path} -v 2>/dev/null", $output, $returnCode);

                if ($returnCode === 0 && !empty($output)) {
                    $versionOutput = implode(' ', $output);
                    // Check if it's CLI and has reasonable version
                    if (strpos($versionOutput, 'cli') !== false &&
                        (strpos($versionOutput, '8.0') !== false ||
                            strpos($versionOutput, '8.1') !== false ||
                            strpos($versionOutput, '8.2') !== false)) {

                        Yii::info("Using universal PHP CLI path: {$path}", 'deployment');
                        return $path;
                    }
                }
            }
        }

        Yii::warning("No optimal PHP CLI path found, using system fallback", 'deployment');
        return 'php';
    }



    /**
     * Log company update
     */
    private function logCompanyUpdate($model, $oldAttributes)
    {
        $adminLog = new LogsAdmin();
        $adminLog->user_id = Yii::$app->user->id;
        $adminLog->action_type = LogsAdmin::ACTION_COMPANY_UPDATE;
        $adminLog->section = 'companies';
        $adminLog->save();

        // Find changed fields with proper type comparison
        $changes = [];
        foreach ($model->attributes as $attribute => $newValue) {
            $oldValue = $oldAttributes[$attribute] ?? null;

            $oldValueStr = (string)$oldValue;
            $newValueStr = (string)$newValue;

            if ($oldValueStr !== $newValueStr) {
                $changes[$attribute] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        // Only log if there are actual changes
        if (!empty($changes)) {
            $updateData = [
                'company_id' => $model->id,
                'company_name' => $model->name,
                'changes' => $changes
            ];

            $adminLog->addDetail($updateData, LogsAdminDetails::TYPE_JSON);
        }
    }

    /**
     * Log company deletion
     */
    private function logCompanyDeletion($companyData)
    {
        $adminLog = new LogsAdmin();
        $adminLog->user_id = Yii::$app->user->id;
        $adminLog->action_type = LogsAdmin::ACTION_COMPANY_DELETE;
        $adminLog->section = 'companies';
        $adminLog->save();

        $deletionData = [
            'deleted_company' => $companyData
        ];

        $adminLog->addDetail($deletionData, LogsAdminDetails::TYPE_JSON);
    }

    /**
     * Finds the Companies model based on its primary key value.
     */
    protected function findModel($id)
    {
        if (($model = Companies::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}