<?php
use yii\helpers\Html;
use yii\helpers\Url;
?>

<div class="container">
    <h2>Deploying: <?= Html::encode($model->name) ?></h2>
    <p>Deployment is in progress. Please wait...</p>

    <div class="alert alert-info">
        <i class="fas fa-spinner fa-spin"></i>
        Setting up CRM system ...
    </div>

    <script>
        // Simple polling instead of SSE
        setTimeout(function() {
            location.reload();
        }, 5000); // Reload every 5 seconds
    </script>
</div>