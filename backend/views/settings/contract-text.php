<?php

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use backend\widgets\tinymce\TinyMceWidget;

/* @var $this yii\web\View */
/* @var $model common\models\Settings */
/* @var $form yii\bootstrap4\ActiveForm */

$this->title = 'Contract Text Settings';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="settings-contract-text">

    <div class="settings-form">

        <?php $form = ActiveForm::begin(); ?>

        <?= $form->field($model, 'contract_text')->widget(TinyMceWidget::class, [
            'preset' => 'contract',
            'clientOptions' => [
                'toolbar_sticky' => true,
                'toolbar_mode' => 'sliding',
                'table_responsive_width' => true,
                'table_header_type' => 'sectionCells',

                'paste_as_text' => false,
                'paste_retain_style_properties' => 'color font-size font-weight text-align',
                'content_style' => 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; } table { border-collapse: collapse; } th, td { padding: 8px; }',


                // Style formats
                'style_formats' => [
                    ['title' => 'Headers', 'items' => [
                        ['title' => 'Heading 1', 'format' => 'h1'],
                        ['title' => 'Heading 2', 'format' => 'h2'],
                        ['title' => 'Heading 3', 'format' => 'h3'],
                        ['title' => 'Heading 4', 'format' => 'h4']
                    ]],
                    ['title' => 'Alignment', 'items' => [
                        ['title' => 'Left align', 'selector' => 'p,h1,h2,h3,h4,h5,h6,td,th,div', 'styles' => ['text-align' => 'left']],
                        ['title' => 'Center', 'selector' => 'p,h1,h2,h3,h4,h5,h6,td,th,div', 'styles' => ['text-align' => 'center']],
                        ['title' => 'Right align', 'selector' => 'p,h1,h2,h3,h4,h5,h6,td,th,div', 'styles' => ['text-align' => 'right']],
                        ['title' => 'Justify', 'selector' => 'p,h1,h2,h3,h4,h5,h6,td,th,div', 'styles' => ['text-align' => 'justify']]
                    ]],
                    ['title' => 'Special', 'items' => [
                        ['title' => 'Variable', 'inline' => 'span', 'styles' => ['background-color' => '#ffffcc', 'padding' => '2px 4px', 'border-radius' => '3px']],
                        ['title' => 'Important', 'inline' => 'strong', 'styles' => ['color' => '#d9534f']]
                    ]]
                ]
            ]
        ])->label('Contract Text') ?>

        <div class="form-group">
            <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-secondary">
                    <h5>Template Information:</h5>
                    <p><strong>How to use templates:</strong> Click the "Templates" button in the TinyMCE toolbar, select the desired template and it will be inserted into the text.</p>
                    <p><strong>Variables:</strong> Use variables in double curly braces (e.g., {{first_name}}) for automatic value replacement when generating documents.</p>
                    <p><strong>Available variables:</strong></p>
                    <ul>
                        <li>{{courier_first_name}} – First name of the courier</li>
                        <li>{{courier_last_name}} – Last name of the courier</li>
                        <li>{{courier_address}} – Address of the courier</li>
                        <li>{{company_name}} – Company name</li>
                        <li>{{company_address}} – Company address</li>
                        <li>{{сurrent_date}} – Current date</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php ActiveForm::end(); ?>

    </div>

</div>