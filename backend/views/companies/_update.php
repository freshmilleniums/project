<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\Companies;

/* @var $this yii\web\View */
/* @var $model common\models\Companies */
/* @var $users array */
/* @var $form yii\widgets\ActiveForm */

$this->registerJs("
$(document).on('click', '#generate-api-key-btn', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: '" . \yii\helpers\Url::to(['companies/generate-api-key']) . "',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#companies-landing_api_key').val(response.apiKey);
            }
        },
        error: function() {
            alert('Error generating API key');
        }
    });
});

$(document).on('click', '#copy-api-key-btn', function(e) {
    e.preventDefault();
    var apiKeyInput = $('#companies-landing_api_key');
    apiKeyInput.select();
    document.execCommand('copy');
    
    var btn = $(this);
    var originalHtml = btn.html();
    btn.html('<i class=\"fas fa-check\"></i> Copied!');
    
    setTimeout(function() {
        btn.html(originalHtml);
    }, 2000);
});
");
?>

<div class="company-update-form">

    <?php $form = ActiveForm::begin([
        'id' => 'company-update-form',
        'action' => ['update', 'id' => $model->id],
        'enableAjaxValidation' => false,
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'status')->dropDownList(Companies::getStatusList()) ?>

    <?= $form->field($model, 'landing_url')->textInput(['maxlength' => true])->hint('Enter the landing page URL (e.g., https://example.com or https://landing.example.com)') ?>

    <div class="form-group">
        <?= Html::activeLabel($model, 'landing_api_key', ['class' => 'control-label']) ?>
        <div class="input-group">
            <?= Html::activeTextInput($model, 'landing_api_key', [
                'class' => 'form-control',
                'maxlength' => true,
                'readonly' => false,
            ]) ?>
            <div class="input-group-append">
                <?= Html::button('<i class="fas fa-sync-alt"></i> Generate', [
                    'class' => 'btn btn-primary',
                    'id' => 'generate-api-key-btn',
                    'title' => 'Generate new API key'
                ]) ?>
                <?= Html::button('<i class="far fa-copy"></i> Copy', [
                    'class' => 'btn btn-secondary',
                    'id' => 'copy-api-key-btn',
                    'title' => 'Copy to clipboard'
                ]) ?>
            </div>
        </div>
        <div class="hint-block">This key will be used by the landing page to authenticate API requests</div>
        <?= Html::error($model, 'landing_api_key', ['class' => 'invalid-feedback']) ?>
    </div>

    <?= $form->field($model, 'administrator_id')->dropDownList($users, [
        'prompt' => 'Select Administrator'
    ])->label('Administrator') ?>

    <hr>

    <h5>SMTP Email Settings</h5>

    <?= $form->field($model, 'smtp_server')->textInput([
        'maxlength' => true,
        'placeholder' => 'smtp.gmail.com or mail.company.com'
    ]) ?>

    <?= $form->field($model, 'smtp_port')->textInput([
        'type' => 'number',
        'min' => 1,
        'max' => 65535,
        'placeholder' => '587'
    ]) ?>

    <?= $form->field($model, 'smtp_login')->textInput([
        'maxlength' => true,
        'placeholder' => 'your-email@domain.com'
    ]) ?>

    <?= $form->field($model, 'smtp_password')->passwordInput([
        'maxlength' => true,
        'placeholder' => 'Enter SMTP password'
    ]) ?>

    <div class="form-group">
        <?= Html::button('Save', ['class' => 'btn btn-success update-company-send']) ?>
        <?= Html::button('Cancel', ['class' => 'btn btn-secondary cancel-action']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>