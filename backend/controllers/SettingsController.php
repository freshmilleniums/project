<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\helpers\Json;
use backend\models\TestItem;
use backend\models\TestItemOption;
use common\models\Settings;

class SettingsController extends BaseController
{
    public function actionTest()
    {
        $testItems = TestItem::find()
            ->with('options')
            ->orderBy('sort ASC')
            ->all();

        return $this->render('test', [
            'testItems' => $testItems
        ]);
    }

    public function actionCreateQuestion()
    {
        $model = new TestItem();

        if ($model->load(Yii::$app->request->post())) {
            // Get options data for validation
            $options = Yii::$app->request->post('options', []);
            $model->setOptionsForValidation($options);

            if ($model->validate()) {
                // Set next sort order
                $maxSort = TestItem::find()->max('sort') ?? 0;
                $model->sort = $maxSort + 1;

                if ($model->save()) {
                    // Save options for radio and checkbox types
                    if (in_array($model->type, [TestItem::TYPE_RADIO, TestItem::TYPE_CHECKBOX])) {
                        foreach ($options as $index => $optionText) {
                            if (!empty(trim($optionText))) {
                                $option = new TestItemOption();
                                $option->test_item_id = $model->id;
                                $option->option_text = trim($optionText);
                                $option->sort = $index;
                                $option->save();
                            }
                        }
                    }

                    Yii::$app->session->setFlash('success', 'Question created successfully.');
                    return $this->redirect(['test']);
                }
            }
        }

        return $this->render('question-form', [
            'model' => $model
        ]);
    }

    public function actionUpdateQuestion($id)
    {
        $model = TestItem::findOne($id);
        if (!$model) {
            throw new \yii\web\NotFoundHttpException('Question not found');
        }

        if ($model->load(Yii::$app->request->post())) {
            // Get options data for validation
            $options = Yii::$app->request->post('options', []);
            $model->setOptionsForValidation($options);

            if ($model->validate()) {
                if ($model->save()) {
                    // Delete existing options
                    TestItemOption::deleteAll(['test_item_id' => $model->id]);

                    // Save new options for radio and checkbox types
                    if (in_array($model->type, [TestItem::TYPE_RADIO, TestItem::TYPE_CHECKBOX])) {
                        foreach ($options as $index => $optionText) {
                            if (!empty(trim($optionText))) {
                                $option = new TestItemOption();
                                $option->test_item_id = $model->id;
                                $option->option_text = trim($optionText);
                                $option->sort = $index;
                                $option->save();
                            }
                        }
                    }

                    Yii::$app->session->setFlash('success', 'Question updated successfully.');
                    return $this->redirect(['test']);
                }
            }
        }

        return $this->render('question-form', [
            'model' => $model
        ]);
    }

    public function actionUpdateSort()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $ids = Yii::$app->request->post('ids', []);

        foreach ($ids as $index => $id) {
            TestItem::updateAll(['sort' => $index], ['id' => $id]);
        }

        return ['success' => true];
    }

    public function actionDeleteQuestion($id)
    {
        $model = TestItem::findOne($id);
        if ($model) {
            // Delete related options
            TestItemOption::deleteAll(['test_item_id' => $id]);
            $model->delete();
        }

        return $this->redirect(['test']);
    }

    /**
     * Contract text settings page
     * @return string
     */
    public function actionContractText()
    {
        $model = Settings::getSettings();
        $model->scenario = Settings::SCENARIO_CONTRACT_TEXT;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Contract text has been updated successfully.');
            return $this->redirect(['contract-text']);
        }

        return $this->render('contract-text', [
            'model' => $model,
        ]);
    }
}