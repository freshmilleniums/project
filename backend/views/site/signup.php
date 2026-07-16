<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\SignupForm $model */

use yii\bootstrap4\Html;
use yii\bootstrap4\ActiveForm;
use common\models\User;

$this->title = 'Signup';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-signup">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Please fill out the following fields to signup:</p>

    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin(['id' => 'form-signup']); ?>

                <?= $form->field($model, 'first_name')->textInput(['autofocus' => true]) ?>

                <?= $form->field($model, 'last_name')->textInput() ?>

                <?= $form->field($model, 'email') ?>

                <?= $form->field($model, 'address') ?>

                <?= $form->field($model, 'phone_number') ?>

                <?= $form->field($model, 'city') ?>

                <?= $form->field($model, 'state')->dropdownList(User::getStates(),['prompt' => 'Choose a state']) ?>

                <?= $form->field($model, 'zip_code') ?>

                <?= $form->field($model, 'password')->passwordInput() ?>

                <?= $form->field($model, 'password_repeat')->passwordInput() ?>

                <div class="form-group">
                    <?= Html::submitButton('Signup', ['class' => 'btn btn-primary', 'name' => 'signup-button']) ?>
                </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
