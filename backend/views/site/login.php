<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap4\ActiveForm $form */
/** @var \common\models\LoginForm $model */

use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;

$this->title = 'Login';
?>

<div class="auth-card-header">
    <h1><i class="fas fa-sign-in-alt mr-2"></i><?= Html::encode($this->title) ?></h1>
</div>

<div class="auth-card-body">
    <p>Please fill out the following fields to login:</p>

    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

    <?= $form->field($model, 'email')->textInput([
        'autofocus' => true,
        'placeholder' => 'Enter your email'
    ])->label('Email') ?>

    <?= $form->field($model, 'password')->passwordInput([
        'placeholder' => 'Enter your password'
    ])->label('Password') ?>

    <div class="form-group">
        <?= Html::submitButton('Login', [
            'class' => 'btn btn-primary btn-block',
            'name' => 'login-button'
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>