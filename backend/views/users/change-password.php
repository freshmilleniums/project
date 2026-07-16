<?php

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

/* @var $this yii\web\View */
/* @var $model backend\models\ChangePasswordForm */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="change-password-form">
    <h4>Change Password</h4>

    <?php $form = ActiveForm::begin([
        'id' => 'change-password-form',
        'options' => ['class' => 'password-form'],
        'enableAjaxValidation' => false,
        'enableClientValidation' => true,
    ]); ?>

    <?= $form->field($model, 'new_password')->passwordInput([
        'maxlength' => true,
        'placeholder' => 'Enter new password',
        'class' => 'form-control'
    ]) ?>

    <?= $form->field($model, 'confirm_password')->passwordInput([
        'maxlength' => true,
        'placeholder' => 'Confirm new password',
        'class' => 'form-control'
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton('Change Password', [
            'class' => 'btn  btn-success change-password-submit',
            'name' => 'change-password-button'
        ]) ?>
        <?= Html::button('Cancel', [
            'class' => 'btn btn-secondary cancel-action'
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>