<?php

/** @var yii\web\View $this */
/** @var string $content */

use backend\assets\AppAsset;
use yii\helpers\Html;

\hail812\adminlte3\assets\FontAwesomeAsset::register($this);
\hail812\adminlte3\assets\AdminLteAsset::register($this);
\hail812\adminlte3\assets\PluginAsset::register($this)->add(['toastr']);
AppAsset::register($this);
$this->registerCssFile('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback');

$assetDir = Yii::$app->assetManager->getPublishedUrl('@vendor/almasaeed2010/adminlte/dist');

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
            body {
                background-color: #f4f6f9;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .auth-wrapper {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .auth-header {
                background-color: #343a40;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                padding: 15px 0;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
            }

            .auth-header .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .auth-header .brand {
                display: flex;
                align-items: center;
                text-decoration: none;
            }

            .auth-header .brand-image {
                width: 40px;
                height: 40px;
                margin-right: 10px;
                opacity: 0.8;
            }

            .auth-header .brand-text {
                font-size: 1.5rem;
                font-weight: 300;
                color: #ffffff;
            }

            .auth-footer {
                background-color: #f5f5f5;
                color: #6c757d;
                padding: 20px 0;
                text-align: center;
                font-size: 0.9em;
                border-top: 1px solid #dee2e6;
            }

            .auth-card {
                background: #ffffff;
                border-radius: 0.25rem;
                box-shadow: 0 0 1px rgba(0,0,0,0.125), 0 1px 3px rgba(0,0,0,0.2);
                overflow: hidden;
                max-width: 500px;
                width: 100%;
                margin-top: 80px;
            }

            .auth-card-header {
                background-color: #6c757d;
                color: white;
                padding: 30px;
                text-align: center;
                border-bottom: 0;
            }

            .auth-card-header h1 {
                margin: 0;
                font-size: 2rem;
                font-weight: 300;
            }

            .auth-card-body {
                padding: 30px;
            }

            .auth-card-body p {
                color: #6c757d;
                margin-bottom: 20px;
            }

            .form-control {
                border-radius: 0.25rem;
                border: 1px solid #ced4da;
                padding: 0.375rem 0.75rem;
                font-size: 1rem;
            }

            .form-control:focus {
                border-color: #80bdff;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            }

            .btn-primary {
                background-color: #28a745;
                border-color: #28a745;
                border-radius: 0.25rem;
                padding: 0.375rem 0.75rem;
                font-size: 1rem;
            }

            .btn-primary:hover {
                background-color: #218838;
                border-color: #1e7e34;
            }

            .btn-primary:focus {
                box-shadow: 0 0 0 0.2rem rgba(40,167,69,0.5);
            }

            .auth-links {
                text-align: center;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e9ecef;
            }

            .auth-links a {
                color: #007bff;
                text-decoration: none;
            }

            .auth-links a:hover {
                color: #0056b3;
                text-decoration: underline;
            }

            /* Custom checkbox styling */
            .custom-control-input:checked ~ .custom-control-label::before {
                background-color: #28a745;
                border-color: #28a745;
            }

            @media (max-width: 576px) {
                .auth-card {
                    margin-top: 70px;
                    border-radius: 0;
                }

                .auth-card-header {
                    padding: 20px;
                }

                .auth-card-header h1 {
                    font-size: 1.5rem;
                }

                .auth-card-body {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body class="hold-transition">
    <?php $this->beginBody() ?>

    <!-- Header -->
    <div class="auth-header">
        <div class="container">
            <a href="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="brand">
                <img src="<?=$assetDir?>/img/AdminLTELogo.png"
                     alt="Employers CRM Logo"
                     class="brand-image">
                <span class="brand-text">Employers CRM</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <?= $content ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="auth-footer">
        <strong>&copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?></strong>
    </div>

    <?php $this->endBody() ?>

    <?php
    // Flash messages
    foreach (Yii::$app->session->getAllFlashes() as $type => $message):
        $toastrType = ($type === 'error') ? 'error' : $type;
        $this->registerJs("
        toastr.{$toastrType}('" . addslashes($message) . "');
    ");
    endforeach;
    ?>

    </body>
    </html>
<?php $this->endPage() ?>