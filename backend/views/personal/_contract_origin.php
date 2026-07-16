<?php
use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

/* @var $this yii\web\View */
/* @var $user \common\models\User */

$this->title = 'Courier contract';
$this->params['breadcrumbs'][] = $this->title;
?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Allura&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Alex+Brush&display=swap');

        .pdf-preview {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">

                <?php if ($user->contract_pdf_path && file_exists(Yii::getAlias('@webroot/uploads/') . $user->contract_pdf_path)): ?>

                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-check-circle"></i> Contract Signed
                            </h3>
                            <div class="card-tools">
                                <?= Html::a(
                                    '<i class="fas fa-download"></i> Download PDF',
                                    ['get-contract'],
                                    ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']
                                ) ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="text">
                                        <strong>Contract signed on:</strong> <?= Yii::$app->formatter->asDate($user->sign_signature_date) ?>
                                    </p>

                                    <!-- PDF Preview -->
                                    <object
                                            class="pdf-preview kv-preview-data"
                                            title="Contract PDF"
                                            data="<?= \yii\helpers\Url::to(['view-contract-pdf']) ?>"
                                            type="application/pdf">
                                        <p>Your browser does not support PDF preview.
                                            <?= Html::a('Click here to download', ['get-contract'], ['target' => '_blank']) ?>
                                        </p>
                                    </object>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="card card-secondary">
                        <div class="card-body">
                            <div class="contract">
                                <?= $this->render('_contract_body', ['user' => $user, 'contractText' => $contractText]) ?>
                                <div class="signature-section">
                                    <p>By signing below, the Courier agrees to the terms and conditions outlined in this Agreement:</p>
                                    <div class="flex" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                                        <div style="width: 30%; text-align: left;">
                                            <strong>Date:</strong>
                                            <?php if ($user->sign_signature_date): ?>
                                                <span class="date-display"><?= Yii::$app->formatter->asDate($user->sign_signature_date) ?></span>
                                            <?php else: ?>
                                                ___________________________
                                            <?php endif; ?>
                                        </div>
                                        <div style="width: 40%; text-align: center;">
                                            <strong>Signature:</strong>
                                            <?php if ($user->sign_signature_date && $user->sign_signature_style): ?>
                                                <span class="signature-display" style="font-family: <?= Html::encode($user->sign_signature_style) ?>;">
                                                <?= Html::encode($user->sign_signature_text ?? '') ?>
                                            </span>
                                            <?php else: ?>
                                                ______________________
                                            <?php endif; ?>
                                        </div>
                                        <div style="width: 30%; text-align: right;">
                                            <strong>Name:</strong> <?= Html::encode(($user->first_name ?? '') .' '. ($user->last_name ?? '')) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-secondary mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Document Signature</h3>
                        </div>
                        <div class="card-body">
                            <?php $form = ActiveForm::begin(['id' => 'sign-contract-form']); ?>

                            <div class="form-group">
                                <div class="row align-items-end">
                                    <? /*
                                    <div class="col-md-4">
                                        <?= $form->field($user, 'sign_signature_style')->dropDownList(
                                            \backend\models\User::getSignatureStyles(),
                                            ['prompt' => 'Select your signature style', 'id' => 'signature-style']
                                        ) ?>
                                    </div>
                                    */ ?>

                                    <div class="col-md-4">
                                        <?= $form->field($user, 'sign_signature_text')->textInput([
                                            'id' => 'signature-text',
                                            'placeholder' => 'Enter your signature text'
                                        ]) ?>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="signature-preview" class="form-label">Preview:</label>
                                        <div id="signature-preview" class="border p-3 bg-light" style="font-family: <?=$user->sign_signature_style?>;font-size: 24px; min-height: 60px; border-radius: 5px;">
                                            <?//= Html::encode($user->first_name . ' ' . $user->last_name) ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <?= Html::submitButton('Sign Contract', ['class' => 'btn btn-success btn-lg']) ?>
                            </div>

                            <?php ActiveForm::end(); ?>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

<?php
$js = <<<JS
/*$('#signature-style').on('change', function() {   
    const style = $(this).val();
    $('#signature-preview').css('font-family', style);
});*/

$('#signature-text').on('input', function() {
    const text = $(this).val();
    $('#signature-preview').text(text);
});
JS;

$this->registerJs($js, \yii\web\View::POS_READY);
?>