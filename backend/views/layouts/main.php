<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use backend\assets\AppAsset;
use yii\helpers\Url;

\hail812\adminlte3\assets\FontAwesomeAsset::register($this);
\hail812\adminlte3\assets\AdminLteAsset::register($this);
\hail812\adminlte3\assets\PluginAsset::register($this)->add(['sweetalert2', 'toastr']);
AppAsset::register($this);
$this->registerCssFile('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback');

$assetDir = Yii::$app->assetManager->getPublishedUrl('@vendor/almasaeed2010/adminlte/dist');

$publishedRes = Yii::$app->assetManager->publish('@vendor/hail812/yii2-adminlte3/src/web/js');
$this->registerJsFile($publishedRes[1].'/control_sidebar.js', ['depends' => '\hail812\adminlte3\assets\AdminLteAsset']);

$this->registerCssFile('@web/css/mobile-responsive-tables.css', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/mobile.js', ['depends' => [\yii\web\JqueryAsset::class]]);

?>
<?php $this->beginPage() ?>
    <!DOCTYPE html>
    <html lang="<?= Yii::$app->language ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php $this->registerCsrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>
        <style>
            .main-sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1037;
                transition: none !important;
            }

            @media (min-width: 992px) {
                body:not(.sidebar-collapse) .main-sidebar {
                    width: 250px !important;
                }

                body:not(.sidebar-collapse) .content-wrapper,
                body:not(.sidebar-collapse) .main-footer {
                    margin-left: 250px !important;
                }

                .main-sidebar .nav-sidebar .nav-link {
                    display: flex !important;
                    align-items: center;
                }

                .main-sidebar .nav-sidebar .nav-link .nav-icon {
                    margin-right: 10px;
                    font-size: 1rem;
                }

                .main-sidebar .nav-sidebar .nav-link p {
                    display: inline-block !important;
                }

                .main-sidebar .brand-text {
                    display: inline-block !important;
                }
            }

            .main-sidebar .nav-sidebar {
                padding-top: 0;
            }

            .sidebar-loading .main-sidebar {
                visibility: hidden;
            }

            .main-sidebar.sidebar-ready {
                visibility: visible;
                opacity: 1;
            }
        </style>
    </head>
    <body class="hold-transition sidebar-mini">
    <?php $this->beginBody() ?>

    <div class="wrapper">
        <?= $this->render('navbar', ['assetDir' => $assetDir]) ?>

        <?= $this->render('sidebar', ['assetDir' => $assetDir]) ?>

        <?= $this->render('content', ['content' => $content, 'assetDir' => $assetDir]) ?>

        <?= $this->render('control-sidebar') ?>

        <?= $this->render('footer') ?>
    </div>

    <?php $this->endBody() ?>

    </body>
    </html>
<?php $this->endPage() ?>