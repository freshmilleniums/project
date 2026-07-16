<?php

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

/* @var $this yii\web\View */
/* @var $model backend\models\User */
/* @var $form yii\bootstrap4\ActiveForm */
/* @var $administrators array */
?>

<div class="user-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'role')->dropdownList(
        ArrayHelper::map(Yii::$app->authManager->getRoles(), 'name', 'description'),
        ['prompt' => 'Select a role']
    ) ?>

    <?php if ($model->role === 'employee'): ?>
        <?= $form->field($model, 'administrator_id')->dropdownList(
            ArrayHelper::map($administrators, 'id', function($admin) {
                return $admin['first_name'] . ' ' . $admin['last_name'];
            }),
            ['prompt' => 'Select Administrator']
        ) ?>

        <?= $form->field($model, 'substatus')->dropdownList(
            \backend\models\User::getSubstatusLabels(),
            ['prompt' => 'Select Status']
        ) ?>
    <?php endif; ?>

    <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'first_name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'last_name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'phone_number')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'home_phone')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'position_title')->textInput(['maxlength' => true, 'placeholder' => 'e.g., Investment Analyst']) ?>

    <?= $form->field($model, 'address')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'city')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'state')->textInput(['maxlength' => true, 'placeholder' => 'e.g., CA, New York, Bavaria']) ?>

    <?= $form->field($model, 'country')->widget(Select2::class, [
        'data' => \common\models\User::getCountries(),
        'options' => [
            'placeholder' => 'Select country...',
            'value' => 'US'
        ],
        'pluginOptions' => [
            'allowClear' => false,
        ],
    ]) ?>

    <?= $form->field($model, 'zip_code')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'hr_source')->textInput(['maxlength' => true, 'placeholder' => 'Where did they come from?']) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>