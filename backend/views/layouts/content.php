<?php
/* @var $content string */

use yii\bootstrap4\Breadcrumbs;
use backend\models\User;
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <?php
                        if (!is_null($this->title)) {
                            echo \yii\helpers\Html::encode($this->title);
                        } else {
                            echo \yii\helpers\Inflector::camelize($this->context->id);
                        }
                        ?>
                    </h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <?php
                    echo Breadcrumbs::widget([
                        'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                        'options' => [
                            'class' => 'breadcrumb float-sm-right'
                        ]
                    ]);
                    ?>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Flash Messages -->
    <div class="content">
        <div class="container-fluid">
            <?php if (Yii::$app->user->can('courier')
                && Yii::$app->user->identity->substatus == User::SUBSTATUS_INTERVIEWED
                && (!Yii::$app->user->identity->contract_pdf_path || !Yii::$app->user->identity->sign_signature_date)
            ): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-file-contract mr-2"></i>
                    A contract has been prepared for you, please review and sign it
                </div>
            <?php endif; ?>

            <?php foreach (Yii::$app->session->getAllFlashes() as $type => $message): ?>
                <?php
                $toastrType = ($type === 'error') ? 'error' : $type;
                $this->registerJs("
                    toastr.{$toastrType}('" . addslashes($message) . "');
                ");
                ?>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- /Flash Messages -->

    <!-- Main content -->
    <div class="content">
        <?= $content ?><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
</div>