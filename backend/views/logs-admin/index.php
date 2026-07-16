<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\LogsAdmin;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\LogsAdminSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Admin Logs';
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="logs-admin-index container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <?= Html::button('<i class="fas fa-filter"></i> Filters', ['class' => 'btn btn-primary mobile-filter-btn']) ?>
                        </div>
                    </div>

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'filterModel' => $searchModel,
                        'tableOptions' => ['class' => 'table table-striped table-bordered'],
                        'columns' => [
                            ['class' => 'yii\grid\SerialColumn'],
                            [
                                'attribute' => 'date',
                                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                            ],

                            [
                                'attribute' => 'user_id',
                                'label' => 'User',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    return $model->user ?
                                        Html::encode($model->user->first_name . ' ' . $model->user->last_name)
                                        : '<span class="text-muted">Unknown</span>';
                                },
                            ],
                            [
                                'attribute' => 'action_type',
                                'filter' => LogsAdmin::getActionTypeList(),
                                'value' => function ($model) {
                                    return LogsAdmin::getActionTypeList()[$model->action_type] ?? $model->action_type;
                                },
                            ],
                            'section',

                            [
                                'label' => 'Details',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    $details = $model->logsAdminDetails; // relation
                                    $out = [];
                                    foreach ($details as $detail) {
                                        if ($detail->data_type === \common\models\LogsAdminDetails::TYPE_TEXT) {
                                            $out[] = Html::encode($detail->data);
                                        } elseif ($detail->data_type === \common\models\LogsAdminDetails::TYPE_JSON) {
                                            $json = json_decode($detail->data, true);
                                            $out[] = '<pre>' . Html::encode(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                                        }
                                    }
                                    return '<div class="log-details">' . implode('<hr>', $out) . '</div>';
                                },
                            ],
                        ],
                        'summaryOptions' => ['class' => 'summary mb-2'],
                        'pager' => [
                            'class' => 'yii\bootstrap4\LinkPager',
                        ]
                    ]); ?>

                </div>
            </div>
        </div>
    </div>
</div>