<?php
use yii\helpers\Html;

/* @var $user \common\models\User */
?>
<style>

    h2 {
        text-align: center;
    }
    .signature-line {
        margin-top: 20px;
        border-top: 1px solid #000;
        width: 200px;
    }
    .content {
        margin: 20px 0;
    }
    .section {
        margin-bottom: 20px;
    }
    .flex {
        display: flex;
        justify-content: space-between;
        margin-top: 50px;
    }
    .left, .right {
        width: 45%;
    }
    .signature-display {
        font-size: 24px;
        color: #0066cc;
        border-bottom: 1px solid #000;
        padding-bottom: 5px;
        min-height: 30px;
        display: inline-block;
        min-width: 200px;
    }
    .date-display {
        border-bottom: 1px solid #000;
        padding-bottom: 5px;
        min-height: 30px;
        display: inline-block;
        min-width: 150px;
    }
</style>

<div class="contract-content">
    <?= $contractText ?>
</div>

