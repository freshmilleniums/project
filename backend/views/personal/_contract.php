<?php
/* @var $user \common\models\User */

use yii\helpers\Html;
use yii\bootstrap4\Html as Bootstrap4Html;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Agreement</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }

        .container-fluid {
            max-width: 100%;
            margin: 0 auto;
        }

        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }

        .signature-section .row {
            display: table-row;
        }

        .signature-section .col-md-4 {
            display: table-cell;
            vertical-align: bottom;
            padding: 0 10px;
            width: 33.33%;
        }

        .signature-display {
            font-size: 24px;
            color: #0066cc;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            display: inline-block;
            min-width: 180px;
            text-align: center;
            margin-top: 5px;
        }

        .signature-alex-brush {
            font-family: alex_brush, cursive;
        }

        .signature-allura {
            font-family: allura, cursive;
        }

        .signature-great-vibes {
            font-family: great_vibes, cursive;
        }

        .signature-pacifico {
            font-family: pacifico, cursive;
        }

        .empty-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            padding-bottom: 2px;
            min-width: 150px;
        }

        h1, h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        .content {
            margin: 20px 0;
        }

        .section {
            margin-bottom: 20px;
        }

        .row {
            display: table-row;
        }

        .col-md-4 {
            display: table-cell;
            vertical-align: top;
            padding: 0 10px;
        }

        p {
            margin-bottom: 10px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-weight-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?= $this->render('_contract_body', ['user' => $user, 'contractText' => $contractText]) ?>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 30%; text-align: left; vertical-align: bottom; padding: 5px;">
                <?php if ($user->sign_signature_date): ?>
                    <span class="date-display" style="border-bottom: 1px solid #000; display: inline-block; min-width: 120px; text-align: center; padding-bottom: 0px;">
                    <?= Yii::$app->formatter->asDate($user->sign_signature_date) ?>
                </span>
                <?php else: ?>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 120px; padding-bottom: 0px;">&nbsp;</span>
                <?php endif; ?>
            </td>
            <td style="width: 40%; text-align: center; vertical-align: bottom; padding: 5px;">
                <?php if ($user->sign_signature_date && isset($signatureBase64) && $signatureBase64): ?>
                    <img src="data:image/png;base64,<?= $signatureBase64 ?>"
                         style="display: inline-block; min-width: 180px; max-width: 250px; height: auto; border-bottom: 1px solid #000; padding-bottom: 3px;"
                         alt="Signature" />
                <?php elseif ($user->sign_signature_date && $user->sign_signature_style): ?>
                    <?php
                    $fontMapping = [
                        'Alex Brush' => 'Brush Script MT, Lucida Handwriting, Apple Chancery, cursive',
                        'Allura' => 'Lucida Handwriting, Apple Chancery, Brush Script MT, cursive',
                        'Great Vibes' => 'Apple Chancery, Brush Script MT, Lucida Handwriting, cursive',
                        'Pacifico' => 'Marker Felt, Bradley Hand ITC, Brush Script MT, cursive'
                    ];

                    $mappedFont = $fontMapping[$user->sign_signature_style] ?? 'Brush Script MT, cursive';
                    $needsItalic = in_array($user->sign_signature_style, ['Allura', 'Great Vibes']) ? 'font-style: italic;' : '';
                    ?>
                    <span class="signature-display" style="font-family: <?= $mappedFont ?>; <?= $needsItalic ?> border-bottom: 1px solid #000; display: inline-block; min-width: 180px; text-align: center; padding-bottom: 3px; font-size: 24px; color: #0066cc; letter-spacing: 1px;">
                     <?= Html::encode($user->sign_signature_text ?? '') ?>
                    </span>
                <?php else: ?>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 180px; padding-bottom: 3px;">&nbsp;</span>
                <?php endif; ?>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: bottom; padding: 5px;">
                <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px; padding-bottom: 0px; text-align: right;">
                <?= Html::encode(($user->first_name ?? '') .' '. ($user->last_name ?? '')) ?>
            </span>
            </td>
        </tr>
    </table>
</div>
</body>
</html>