<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\bootstrap4\ActiveForm;
use kartik\file\FileInput;
use common\models\Packages;
use common\models\PackagesDocuments;
use backend\widgets\dynamicForm\DynamicFormWidget;

/* @var $this yii\web\View */
/* @var $model common\models\Packages */
/* @var $packagesDocuments PackagesDocuments[] */

$this->title = 'Package: '. $model->name;
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

if (count($packagesDocuments) == 0) {
    $packagesDocuments = [new PackagesDocuments()];
}
?>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">

                        <?= DetailView::widget([
                            'model' => $model,
                            'attributes' => [
                                'name',
                                'description',
                                'address',
                                [
                                    'attribute' => 'status',
                                    'value' => $model->getStatusName(),
                                ],
                                /*[
                                    'attribute' => 'created_at',
                                    'value' => date('d.m.Y H:i', $model->created_at),
                                ],*/
                                [
                                    'attribute' => 'delivery_date',
                                    'format' => ['date', 'php:Y-m-d H:i'],
                                ],
                                'track',
                                'weight',
                                'comment',
                            ],
                        ]) ?>

                        <!-- Package Documents Section -->
                        <div class="mt-4">
                            <?php if ($model->status == Packages::STATUS_IN_PROGRESS): ?>
                                <!-- Documents Upload Form -->
                                <div class="card">
                                    <div class="card-body">
                                        <?php $form = ActiveForm::begin([
                                            'id' => 'documents-form',
                                            'options' => ['enctype' => 'multipart/form-data']
                                        ]); ?>

                                        <!-- Documents Section -->
                                        <div class="row">
                                            <div class="col-12">
                                                <?php DynamicFormWidget::begin([
                                                    'widgetContainer' => 'documents_dynamic_form_wrapper',
                                                    'widgetBody' => '.documents_container-items',
                                                    'widgetItem' => '.documents_item',
                                                    'min' => 1,
                                                    'insertButton' => '.add-document',
                                                    'deleteButton' => '.remove-document',
                                                    'model' => $packagesDocuments[0],
                                                    'formId' => $form->getId(),
                                                    'formFields' => [
                                                        'file',
                                                    ],
                                                ]); ?>

                                                <div class="panel panel-default">
                                                    <div class="panel-heading">
                                                        <h6 class="panel-title"><?= Yii::t('app', 'Documents') ?></h6>
                                                        <button type="button" class="btn btn-success btn-sm add-document float-right">
                                                            <i class="fa fa-plus"></i> <?= Yii::t('app', 'Add Document') ?>
                                                        </button>
                                                        <div class="clearfix"></div>
                                                    </div>
                                                    <div class="panel-body documents_container-items">
                                                        <?php foreach ($packagesDocuments as $index => $packageDocument): ?>
                                                            <div class="documents_item card mb-3">
                                                                <div class="card-header">
                                                                    <span class="panel-title-document"><?= Yii::t('app', 'Document') ?>: <?= ($index + 1) ?></span>
                                                                    <button type="button" class="btn btn-danger btn-sm remove-document float-right">
                                                                        <i class="fa fa-minus"></i>
                                                                    </button>
                                                                    <div class="clearfix"></div>
                                                                </div>
                                                                <div class="card-body">
                                                                    <?php if (!$packageDocument->isNewRecord) {
                                                                        echo Html::activeHiddenInput($packageDocument, "[{$index}]id");
                                                                    } ?>

                                                                    <?php
                                                                    $initialPreview = [];
                                                                    if ($packageDocument->path) {
                                                                        $initialPreview[] = $packageDocument->getUrl();
                                                                    }

                                                                    echo $form->field($packageDocument, "[{$index}]file")->widget(FileInput::class, [
                                                                        'pluginOptions' => [
                                                                            'initialPreview' => $initialPreview,
                                                                            'initialCaption' => $packageDocument->getFileName(),
                                                                            'showUpload' => false,
                                                                            'initialPreviewFileType' => !empty(pathinfo($packageDocument->path)['extension']) ?
                                                                                (in_array(pathinfo($packageDocument->path)['extension'], ['pdf']) ? 'text' : 'image') : 'image',
                                                                            'initialPreviewAsData' => true,
                                                                            'overwriteInitial' => true,
                                                                            'initialPreviewShowDelete' => true,
                                                                            'allowedFileExtensions' => ['jpg', 'gif', 'png', 'jpeg', 'pdf'],
                                                                            'fileActionSettings' => ['showZoom' => true],
                                                                            'browseClass' => 'btn btn-primary',
                                                                            'browseIcon' => '<i class="fas fa-folder-open"></i> ',
                                                                            'browseLabel' => 'Select File'
                                                                        ],
                                                                    ]);
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <?php DynamicFormWidget::end(); ?>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <?= Html::submitButton('<i class="fas fa-check"></i> Mark as Delivered', [
                                                'class' => 'btn btn-success'
                                            ]) ?>
                                        </div>

                                        <?php ActiveForm::end(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Existing Documents Display -->
                            <?php if ($model->documents): ?>
                                <div class="mt-4">
                                    <h5>Uploaded Documents</h5>
                                    <div class="row">
                                        <?php foreach ($model->documents as $document): ?>
                                            <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3">
                                                <div class="card shadow-sm" style="height: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 8px;">
                                                    <div class="card-body p-1 d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 180px;">
                                                        <?php if ($document->isImage()): ?>
                                                            <a href="<?= $document->getUrl() ?>" target="_blank">
                                                                <img src="<?= $document->getUrl() ?>"
                                                                     class="img-thumbnail mb-2"
                                                                     style="max-width: 100%;"
                                                                     alt="Document">
                                                            </a>
                                                        <?php elseif ($document->isPdf()): ?>
                                                            <i class="fas fa-file-pdf fa-2x text-danger mb-1"></i>
                                                            <div class="small text-truncate" style="max-width: 100%">
                                                                <?= Html::encode($document->getFileName()) ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <i class="fas fa-file fa-2x text-secondary mb-1"></i>
                                                            <div class="small text-truncate" style="max-width: 100%">
                                                                <?= Html::encode($document->getFileName()) ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="mt-1">
                                                            <?= Html::a(
                                                                '<i class="fas fa-download"></i>',
                                                                ['download-package-document', 'id' => $document->id],
                                                                ['class' => 'btn btn-sm btn-outline-primary', 'title' => 'Download']
                                                            ) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-4">
                            <?php if ($model->status == Packages::STATUS_NEW): ?>
                                <?= Html::a(
                                    '<i class="fas fa-play"></i> Package received',
                                    ['start-package', 'id' => $model->id],
                                    [
                                        'class' => 'btn btn-success',
                                        'data' => [
                                            'confirm' => 'Are you sure you want to receive this package?',
                                            'method' => 'post',
                                        ],
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <?= Html::a('<i class="fas fa-arrow-left"></i> Back to Packages List', ['packages'], ['class' => 'btn btn-secondary']) ?>
                        </div>

                    </div>
                    <!--.col-md-12-->
                </div>
                <!--.row-->
            </div>
            <!--.card-body-->
        </div>
        <!--.card-->
    </div>

<?php
$css = <<<'EOD'
.img-thumbnail {
    cursor: pointer;
}

.card .card-body {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.btn-group-sm > .btn, .btn-sm {
    margin: 2px;
}
EOD;

$this->registerCss($css);

$js = <<<'EOD'

$(".documents_dynamic_form_wrapper").on("afterInsert", function(e, item) {
    $(".documents_dynamic_form_wrapper .panel-title-document").each(function(index) {
        $(this).html("Document: " + (index + 1));
    });
    $(item).find('.fileinput-remove').click();
});

$(".documents_dynamic_form_wrapper").on("afterDelete", function(e, item) {
    $(".documents_dynamic_form_wrapper .panel-title-document").each(function(index) {
        $(this).html("Document: " + (index + 1));
    });
});

$('.kv-file-remove').on('click', function(e){
    var filePreview = $(this).closest('.file-input').find('.fileinput-remove');
    if(filePreview){
        filePreview[0].click();
    }
});

EOD;

$this->registerJs($js, \yii\web\View::POS_END);
?>