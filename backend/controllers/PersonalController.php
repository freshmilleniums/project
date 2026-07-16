<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use backend\models\TestItem;
use backend\models\TestUserAnswer;
use backend\models\User;
use common\models\Packages;
use backend\models\PackagesSearch;
use common\models\Tasks;
use backend\models\TasksSearch;
use common\models\TasksDocuments;
use common\models\TasksLabels;
use common\models\PackagesDocuments;
use common\models\Settings;
use backend\models\MultipleModel;
use yii\helpers\Html;
use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\FontMetrics;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Personal controller for user personal area
 */
class PersonalController extends BaseController
{

    /**
     * Test page action
     * @return mixed
     */
    public function actionTest()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);

        // Check if user has already completed the test
        if ($user->substatus != User::SUBSTATUS_WAITING_FOR_CALL &&
            $user->substatus != User::SUBSTATUS_TAKING_TEST) {
            // Show test results instead of test form
            return $this->testResults();
        }

        // Get all test items with their options
        $testItems = TestItem::find()
            ->with('options')
            ->orderBy('sort ASC, id ASC')
            ->all();

        // Get existing user answers
        $existingAnswers = TestUserAnswer::findAllByUserId($userId);

        // Process form submission
        if (Yii::$app->request->isPost) {
            $answers = Yii::$app->request->post('answers', []);
            $completeTest = Yii::$app->request->post('complete_test', false);

            // Validate answers if completing test
            if ($completeTest) {
                $validationErrors = $this->validateAllAnswersProvided($testItems, $answers);

                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        Yii::$app->session->setFlash('error', $error);
                    }

                    // Re-render form with validation errors
                    return $this->render('test', [
                        'testItems' => $testItems,
                        'existingAnswers' => $existingAnswers,
                    ]);
                }
            }

            // Save answers
            try {
                $this->saveUserAnswers($userId, $answers, $testItems, $completeTest);

                if ($completeTest) {
                    // Mark test as completed
                    $user->substatus = User::SUBSTATUS_TEST_PASSED;
                    $user->save();

                    Yii::$app->session->setFlash('success', 'Test completed successfully! Your answers have been submitted.');
                    return $this->redirect(['test']);
                } else {
                    Yii::$app->session->setFlash('success', 'Your answers have been saved successfully!');
                    return $this->redirect(['test']);
                }
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'Error saving answers: ' . $e->getMessage());
            }
        }

        return $this->render('test', [
            'testItems' => $testItems,
            'existingAnswers' => $existingAnswers,
        ]);
    }

    public function actionContract()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);
        $user->scenario = 'sign_contract';
        $user->sign_signature_style = User::SIGN_STYLE_PACIFICO ;

        /*
        if (!$user->contract_pdf_path) {
            throw new NotFoundHttpException('Contract PDF not found.');
        }

        $filePath = Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path;

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Contract PDF file not found.');
        }

        return Yii::$app->response->sendFile($filePath, 'contract.pdf', [
            'mimeType' => 'application/pdf',
            'inline' => true
        ]);*/

        // Get contract text from settings
        $contractText = Settings::getContractText();

        // Replace variables with actual user data
        $variables = [
            '{{courier_first_name}}' => Html::encode($user->first_name ?? ''),
            '{{courier_last_name}}' => Html::encode($user->last_name ?? ''),
            '{{courier_address}}' => Html::encode($user->address ?? ''),
            '{{сurrent_date}}' => date('m/d/Y'),
            '{{company_name}}' => "Test Company",
            '{{company_address}}' => "Test Company address",
        ];

        $processedContractText = str_replace(array_keys($variables), array_values($variables), $contractText);


        if ($user->load(Yii::$app->request->post()) /*&& $user->validate(['sign_signature_style'])*/) {
            $user->sign_signature_date = date('Y-m-d');
            $user->substatus = User::SUBSTATUS_SIGNED_CONTRACT;
            if ($user->save()) {
                $this->generateAndSaveContractPdf($user);
                Yii::$app->session->setFlash('success', 'Contract signed successfully!');
                return $this->redirect(['contract']);
            }
        }

        return $this->render('_contract_origin', [
            'user' => $user,
            'contractText' => $processedContractText,
        ]);
    }

    private function generateAndSaveContractPdf($user)
    {
        try {
            $options = new Options();
            $options->set('isRemoteEnabled', TRUE);
            $options->set('fontDir', '/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts');
            $options->set('fontCache', '/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/cache');
            $options->set('defaultFont', 'Arial');

            $dompdf = new Dompdf($options);

  /*
            $fontMetrics = $dompdf->getFontMetrics();
            $fontMetrics->installFont('/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/AlexBrush-Regular.ttf', 'Alex Brush');
            $fontMetrics->installFont('/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/Allura-Regular.ttf', 'Allura');
            $fontMetrics->installFont('/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/GreatVibes-Regular.ttf', 'Great Vibes');
            $fontMetrics->installFont('/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/Pacifico-Regular.ttf', 'Pacifico');
*/

            $signatureBase64 = null;
            if ($user->sign_signature_text && $user->sign_signature_style) {
                $signatureBase64 = $this->generateSignatureImage(
                    $user->sign_signature_text,
                    $user->sign_signature_style
                );
            }


            // Get contract text from settings
            $contractText = Settings::getContractText();

            // Replace variables with actual user data
            $variables = [
                '{{courier_first_name}}' => Html::encode($user->first_name ?? ''),
                '{{courier_last_name}}' => Html::encode($user->last_name ?? ''),
                '{{courier_address}}' => Html::encode($user->address ?? ''),
                '{{сurrent_date}}' => date('m/d/Y'),
                '{{company_name}}' => "Test Company",
                '{{company_address}}' => "Test Company address",
            ];

            $processedContractText = str_replace(array_keys($variables), array_values($variables), $contractText);

            $content = $this->renderPartial('_contract', [
                'user' => $user,
                'contractText' => $processedContractText,
                'signatureBase64' => $signatureBase64,
            ]);

            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $originalFileName = 'contract_' . $user->id . '_' . time();
            $fileNewName = md5($originalFileName . time());

            $fileDir = 'contract/' . $fileNewName[0] . '/' . $fileNewName[1] . $fileNewName[2] . '/'; // Исправил [3] на [2]


            $path = Yii::$app->params['uploadPath'] . $fileDir;

            if (!is_dir($path)) {
                FileHelper::createDirectory($path, 0755, true);
            }

            $fileName = 'contract_' . $fileNewName . '.pdf';
            $filePath = $path . $fileName;

            $relativePath = $fileDir . $fileName;

            file_put_contents($filePath, $dompdf->output());

            if (!file_exists($filePath)) {
                throw new Exception("Failed to save PDF file: " . $filePath);
            }

            $user->contract_pdf_path = $relativePath;
            if (!$user->save(false)) {
                throw new Exception("Failed to save contract path to database");
            }

            Yii::info("Contract PDF saved: " . $filePath, __METHOD__);
            return true;

        } catch (Exception $e) {
            Yii::error("Error generating contract PDF: " . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('warning', 'Contract signed but PDF generation failed.');
            return false;
        }
    }


    private function generateSignatureImage($text, $fontStyle)
    {
        try {
            // Increase dimensions for better quality (will scale down later)
            $scale = 2; // Scale factor for anti-aliasing
            $width = 300 * $scale;
            $height = 80 * $scale;

            // Create a high-resolution transparent image
            $image = imagecreatetruecolor($width, $height);

            // Enable alpha channel for transparency
            imagealphablending($image, false);
            imagesavealpha($image, true);

            // Transparent background
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefill($image, 0, 0, $transparent);

            // Text color (blue, similar to original)
            $textColor = imagecolorallocate($image, 0, 102, 204); // #0066cc

            // Define font path
            $fontsPath = '/var/www/crm_delivery_usr/data/www/crm-delivery.site/backend/web/fonts/';
            $fontFiles = [
                'Alex Brush' => 'AlexBrush-Regular.ttf',
                'Allura' => 'Allura-Regular.ttf',
                'Great Vibes' => 'GreatVibes-Regular.ttf',
                'Pacifico' => 'Pacifico-Regular.ttf'
            ];

            $fontFile = $fontsPath . ($fontFiles[$fontStyle] ?? $fontFiles['Alex Brush']);

            // Check if font file exists
            if (!file_exists($fontFile)) {
                throw new Exception("Font file not found: {$fontFile}");
            }

            // Increase font size for high resolution
            $fontSize = 24 * $scale;

            // Get text bounding box for centering
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
            $textWidth = $bbox[4] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];

            // Calculate position for centering and slightly shift down
            $x = ($width - $textWidth) / 2;
            $y = ($height - $textHeight) / 2 + $textHeight + (20 * $scale); // Add 20px (scaled)

            // Render text onto the image
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $text);

            // Downscale image for anti-aliasing
            $finalImage = imagecreatetruecolor(300, 80);
            imagealphablending($finalImage, false);
            imagesavealpha($finalImage, true);

            $finalTransparent = imagecolorallocatealpha($finalImage, 255, 255, 255, 127);
            imagefill($finalImage, 0, 0, $finalTransparent);

            // Resample with high quality
            imagecopyresampled($finalImage, $image, 0, 0, 0, 0, 300, 80, $width, $height);

            // Create buffer and encode to PNG
            ob_start();
            imagepng($finalImage, null, 9); // Max compression
            $imageData = ob_get_contents();
            ob_end_clean();

            // Free memory
            imagedestroy($image);
            imagedestroy($finalImage);

            Yii::info("Signature image generated for font: {$fontStyle}", __METHOD__);

            // Return Base64-encoded image
            return base64_encode($imageData);

        } catch (Exception $e) {
            Yii::error("Error generating signature image: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    public function actionGetContract()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);

        if ($user->contract_pdf_path && file_exists(Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path)) {
            $filePath = Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path;

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="contract.pdf"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            readfile($filePath);
            exit;
        }

        $content = $this->renderPartial('_contract', [
            'user' => $user,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', TRUE);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return Yii::$app->response->sendContentAsFile(
            $dompdf->output(),
            'contract.pdf',
            [
                'mimeType' => 'application/pdf',
                'inline' => true
            ]
        );
    }

    public function actionViewContractPdf()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);

        if (!$user->contract_pdf_path || !file_exists(Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path)) {
            throw new NotFoundHttpException('Contract PDF not found.');
        }

        $filePath = Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path;

        return Yii::$app->response->sendFile($filePath, 'contract.pdf', [
            'mimeType' => 'application/pdf',
            'inline' => true
        ]);
    }

  /*  public function actionTest2()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);

        // Check if user has already completed the test
        if ($user->substatus != User::SUBSTATUS_WAITING_FOR_CALL &&
            $user->substatus != User::SUBSTATUS_TAKING_TEST) {
            // Show test results instead of test form
            return $this->testResults();
        }

        // Get all test items with their options
        $testItems = TestItem::find()
            ->with('options')
            ->orderBy('sort ASC, id ASC')
            ->all();

        // Get existing user answers
        $existingAnswers = TestUserAnswer::findAllByUserId($userId);

        // Process form submission
        if (Yii::$app->request->isPost) {
            $answers = Yii::$app->request->post('answers', []);
            $completeTest = Yii::$app->request->post('complete_test', false);

            // Validate answers if completing test
            if ($completeTest) {
                $validationErrors = $this->validateAllAnswersProvided($testItems, $answers);

                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        Yii::$app->session->setFlash('error', $error);
                    }

                    // Re-render form with validation errors
                    return $this->render('test', [
                        'testItems' => $testItems,
                        'existingAnswers' => $existingAnswers,
                    ]);
                }
            }

            // Save answers
            try {
                $this->saveUserAnswers($userId, $answers, $testItems, $completeTest);

                if ($completeTest) {
                    // Mark test as completed
                    $user->substatus = User::SUBSTATUS_TEST_PASSED;
                    $user->save();

                    Yii::$app->session->setFlash('success', 'Test completed successfully! Your answers have been submitted.');
                    return $this->redirect(['test']);
                } else {
                    Yii::$app->session->setFlash('success', 'Your answers have been saved successfully!');
                    return $this->redirect(['test']);
                }
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'Error saving answers: ' . $e->getMessage());
            }
        }

        return $this->render('test2', [
            'testItems' => $testItems,
            'existingAnswers' => $existingAnswers,
        ]);
    }*/

    /**
     * Validate that all questions have answers when completing test
     * @param TestItem[] $testItems
     * @param array $answers
     * @return array Array of validation error messages
     */
    private function validateAllAnswersProvided($testItems, $answers)
    {
        $errors = [];
        $questionNumber = 1;

        foreach ($testItems as $testItem) {
            $itemId = $testItem->id;
            $answer = isset($answers[$itemId]) ? $answers[$itemId] : null;

            $isEmpty = false;

            switch ($testItem->type) {
                case TestItem::TYPE_TEXT:
                    $isEmpty = empty(trim($answer));
                    break;

                case TestItem::TYPE_RADIO:
                    $isEmpty = empty($answer);
                    break;

                case TestItem::TYPE_CHECKBOX:
                    $isEmpty = !is_array($answer) || empty(array_filter($answer));
                    break;

                default:
                    $isEmpty = empty($answer);
                    break;
            }

            if ($isEmpty) {
                $errors[] = "Question {$questionNumber}: \"{$testItem->question_name}\" must be answered to complete the test.";
            }

            $questionNumber++;
        }

        return $errors;
    }

    /**
     * Save user answers to database
     * @param int $userId
     * @param array $answers
     * @param TestItem[] $testItems
     */
    private function saveUserAnswers($userId, $answers, $testItems, $isCompleting = false)
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            // Delete all previous answers for this user
            TestUserAnswer::deleteAllByUserId($userId);

            // Create new answers
            foreach ($testItems as $testItem) {
                $itemId = $testItem->id;
                $answer = isset($answers[$itemId]) ? $answers[$itemId] : null;

                // When completing test, all answers should be present (validated above)
                // When just saving, skip empty answers
                if (!$isCompleting && empty($answer)) {
                    continue;
                }

                // For completing test, we still need to handle the case where answer might be empty
                // (though validation should prevent this)
                if ($isCompleting && empty($answer)) {
                    throw new \Exception('Missing answer for question: ' . $testItem->question_name);
                }

                // Create and save answer
                $userAnswer = TestUserAnswer::createFromTestItem($userId, $testItem, $answer);

                if (!$userAnswer->save()) {
                    throw new \Exception('Failed to save answer for question: ' . $testItem->question_name);
                }
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function testResults()
    {
        $userId = Yii::$app->user->id;

        // Get user's test answers
        $userAnswers = TestUserAnswer::find()
            ->where(['user_id' => $userId])
            ->orderBy('id ASC')
            ->all();

        return $this->render('test_results', [
            'userAnswers' => $userAnswers
        ]);
    }

    public function actionTasks()
    {
        $searchModel = new TasksSearch();
        $searchModel->courier_id = Yii::$app->user->id;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('tasks', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionPackages()
    {
        $searchModel = new PackagesSearch();
        $searchModel->courier_id = Yii::$app->user->id;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('packages', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionTask($id)
    {
        $model = $this->findTaskModel($id);

        $tasksDocuments = $model->documents;

        if (count($tasksDocuments) == 0) {
            $tasksDocuments = [new TasksDocuments()];
        }

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $oldDocuments = $model->documents;

            $tasksDocuments = MultipleModel::createMultiple(TasksDocuments::class, $oldDocuments);
            MultipleModel::loadMultiple($tasksDocuments, Yii::$app->request->post());

            // Check if at least one document is being uploaded for new records or exists
            $hasDocuments = false;
            foreach ($tasksDocuments as $document) {
                if (!$document->isNewRecord || $document->file) {
                    $hasDocuments = true;
                    break;
                }
            }

            // Additional check for uploaded files
            $uploadedFiles = [];
            foreach ($tasksDocuments as $index => $document) {
                $file = UploadedFile::getInstance($document, "[{$index}]file");
                if ($file) {
                    $uploadedFiles[] = $file;
                }
            }

            if (!$hasDocuments && empty($uploadedFiles)) {
                Yii::$app->session->setFlash('error', 'At least one document must be uploaded!');
                $tasksDocuments[0]->addError('file', 'At least one file must be uploaded.');
                return $this->render('task', [
                    'model' => $model,
                    'tasksDocuments' => $tasksDocuments,
                ]);
            }


            $deletedDocumentsIDs = array_diff(
                array_map(function($model) { return $model->id; }, $oldDocuments),
                array_filter(array_map(function($model) { return $model->id; }, $tasksDocuments))
            );

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $model->status = Tasks::STATUS_DELIVERED;
                $flag = $model->save(false);

                if ($flag) {
                    // Delete removed documents
                    if (!empty($deletedDocumentsIDs)) {
                        TasksDocuments::deleteAll(['id' => $deletedDocumentsIDs]);
                    }
                }

                if ($flag) {
                    foreach ($tasksDocuments as $index => $taskDocument) {
                        $taskDocument->task_id = $model->id;
                        $taskDocument->file = UploadedFile::getInstance($taskDocument, "[{$index}]file");

                        if ($taskDocument->file) {
                            if ($taskDocument->upload()) {
                                if (!$taskDocument->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                $flag = false;
                                break;
                            }
                        } else {
                            // Save without file upload if no new file but document exists
                            if (!$taskDocument->isNewRecord) {
                                if (!$taskDocument->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($flag) {
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', 'Documents updated successfully!');
                    return $this->redirect(['task', 'id' => $id]);
                } else {
                    $transaction->rollBack();
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }

        }

        return $this->render('task', [
            'model' => $model,
            'tasksDocuments' => $tasksDocuments,
        ]);
    }

    public function actionPackage($id)
    {
        $model = $this->findPackageModel($id);

        $packagesDocuments = $model->documents;

        if (count($packagesDocuments) == 0) {
            $packagesDocuments = [new PackagesDocuments()];
        }

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $oldDocuments = $model->documents;

            $packagesDocuments = MultipleModel::createMultiple(PackagesDocuments::class, $oldDocuments);
            MultipleModel::loadMultiple($packagesDocuments, Yii::$app->request->post());

            // Check if at least one document is being uploaded for new records or exists
            $hasDocuments = false;
            foreach ($packagesDocuments as $document) {
                if (!$document->isNewRecord || $document->file) {
                    $hasDocuments = true;
                    break;
                }
            }

            // Additional check for uploaded files
            $uploadedFiles = [];
            foreach ($packagesDocuments as $index => $document) {
                $file = UploadedFile::getInstance($document, "[{$index}]file");
                if ($file) {
                    $uploadedFiles[] = $file;
                }
            }

            if (!$hasDocuments && empty($uploadedFiles)) {
                Yii::$app->session->setFlash('error', 'At least one document must be uploaded!');
                $packagesDocuments[0]->addError('file', 'At least one file must be uploaded.');
                return $this->render('package', [
                    'model' => $model,
                    'packagesDocuments' => $packagesDocuments,
                ]);
            }

            $deletedDocumentsIDs = array_diff(
                array_map(function($model) { return $model->id; }, $oldDocuments),
                array_filter(array_map(function($model) { return $model->id; }, $packagesDocuments))
            );

            $transaction = Yii::$app->db->beginTransaction();

            try {
                $model->status = Packages::STATUS_DELIVERED;
                $flag = $model->save(false);

                if ($flag) {
                    // Delete removed documents
                    if (!empty($deletedDocumentsIDs)) {
                        PackagesDocuments::deleteAll(['id' => $deletedDocumentsIDs]);
                    }
                }

                if ($flag) {
                    foreach ($packagesDocuments as $index => $packageDocument) {
                        $packageDocument->package_id = $model->id;
                        $packageDocument->file = UploadedFile::getInstance($packageDocument, "[{$index}]file");

                        if ($packageDocument->file) {
                            if ($packageDocument->upload()) {
                                if (!$packageDocument->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                $flag = false;
                                break;
                            }
                        } else {
                            // Save without file upload if no new file but document exists
                            if (!$packageDocument->isNewRecord) {
                                if (!$packageDocument->save(false)) {
                                    $flag = false;
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($flag) {
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', 'Documents updated successfully!');
                    return $this->redirect(['package', 'id' => $id]);
                } else {
                    $transaction->rollBack();
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        return $this->render('package', [
            'model' => $model,
            'packagesDocuments' => $packagesDocuments,
        ]);
    }

    /**
     * Start package execution
     */
    public function actionStartPackage($id)
    {
        $model = $this->findPackageModel($id);

        // Check if package is in correct status
        if ($model->status != Packages::STATUS_NEW) {
            Yii::$app->session->setFlash('error', 'Package cannot be started in current status.');
            return $this->redirect(['package', 'id' => $id]);
        }

        $model->status = Packages::STATUS_IN_PROGRESS;

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Package started successfully!');
        } else {
            Yii::$app->session->setFlash('error', 'Error starting the package.');
        }

        return $this->redirect(['package', 'id' => $id]);
    }

    /**
     * Download package document file
     */
    public function actionDownloadPackageDocument($id)
    {
        $document = \common\models\PackagesDocuments::findOne($id);

        if (!$document) {
            throw new NotFoundHttpException('Document not found.');
        }

        // Check if user has access to this document's package
        $package = $this->findPackageModel($document->package_id);

        if (!file_exists($document->getFilePath())) {
            throw new NotFoundHttpException('File not found.');
        }

        return Yii::$app->response->sendFile(
            $document->getFilePath(),
            $document->getFileName(),
            ['inline' => false]
        );
    }

    /**
     * Download task document file
     */
    public function actionDownloadDocument($id)
    {
        $document = \common\models\TasksDocuments::findOne($id);

        if (!$document) {
            throw new NotFoundHttpException('Document not found.');
        }

        // Check if user has access to this document's task
        $task = $this->findTaskModel($document->task_id);

        if (!file_exists($document->getFilePath())) {
            throw new NotFoundHttpException('File not found.');
        }

        return Yii::$app->response->sendFile(
            $document->getFilePath(),
            $document->getFileName(),
            ['inline' => false]
        );
    }

    protected function findTaskModel($id)
    {
        $courierId = Yii::$app->user->id;

        if (($model = Tasks::findOne(['id' => $id, 'courier_id' => $courierId])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested task does not exist or you do not have access to it.');
    }

    protected function findPackageModel($id)
    {
        $courierId = Yii::$app->user->id;

        if (($model = Packages::findOne(['id' => $id, 'courier_id' => $courierId])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested package does not exist or you do not have access to it.');
    }


    /**
     * Start task execution
     */
    public function actionStartTask($id)
    {
        $model = $this->findTaskModel($id);

        // Check if task is in correct status
        if ($model->status != Tasks::STATUS_NEW) {
            Yii::$app->session->setFlash('error', 'Task cannot be started in current status.');
            return $this->redirect(['task', 'id' => $id]);
        }

        $model->status = Tasks::STATUS_IN_PROGRESS;

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Task started successfully!');
        } else {
            Yii::$app->session->setFlash('error', 'Error starting the task.');
        }

        return $this->redirect(['task', 'id' => $id]);
    }

    /**
     * Download label file
     */
    public function actionDownloadLabel($id)
    {
        $label = \common\models\TasksLabels::findOne($id);

        if (!$label) {
            throw new NotFoundHttpException('Label not found.');
        }

        // Check if user has access to this label's task
        $task = $this->findTaskModel($label->task_id);

        if (!file_exists($label->getFilePath())) {
            throw new NotFoundHttpException('File not found.');
        }

        return Yii::$app->response->sendFile(
            $label->getFilePath(),
            $label->getFileName(),
            ['inline' => false]
        );
    }
}