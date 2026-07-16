<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\TasksSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Tasks';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <?php if (Yii::$app->user->can('createTask')){ ?>
                                <?= Html::a('Create Tasks', ['create'], ['class' => 'btn btn-success']) ?>
                            <?php } ?>
                        </div>
                    </div>


                    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'filterModel' => $searchModel,
                        'columns' => [
                            ['class' => 'yii\grid\SerialColumn'],

                            // 'id',
                            //'user_id',
                            //'courier_id',
                            //'name',
                            //'description',

                            //'created_at',
                            [
                                'attribute' => 'delivery_date',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    if (!empty($model->delivery_date)) {
                                        return date('m/d/Y H:i', is_numeric($model->delivery_date) ? $model->delivery_date : strtotime($model->delivery_date));
                                    }
                                    return '';
                                },
                            ],
                            [
                                'label' => 'Track, Post',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    $result = '';

                                    // Track
                                    if (!empty($model->track)) {
                                        $result .= Html::encode($model->track)  ;
                                    }

                                    // Tracking Status and Age
                                    if ($model->track_status !== null) {
                                        $statusAge = $model->getTrackStatusAge();

                                        $result .= '<br><span >' . Html::encode($model->track_status) . '</span>';

                                        if ($statusAge) {
                                            $result .= '<br><small class="text-muted">' . Html::encode($statusAge) . '</small>';
                                        }
                                    }

                                    $result .= '<br>';

                                    // Post
                                    if ($model->post != \common\models\Packages::POST_NONE) {
                                        $result .= Html::encode($model->getPostName());
                                    }

                                    return $result;
                                },
                                'filter' => false,
                            ],
                            [
                                'label' => 'Weight, Name, Description',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    $result = '';

                                    // Weight
                                    if (!empty($model->weight)) {
                                        $result .= number_format($model->weight, 2) ;
                                    }
                                    $result .= '<br>';

                                    // Name on Task
                                    $result .= Html::encode($model->name) . '<br>';

                                    // Description
                                    if (!empty($model->description)) {
                                        $result .= Html::encode($model->description);
                                    }

                                    return $result;
                                },
                                'filter' => false,
                            ],
                            [
                                'attribute' => 'status',
                                'value' => function ($model) {
                                    return $model->getStatusName();
                                },
                                'filter' => \common\models\Tasks::getStatusList(),
                            ],

                            [
                                'class' => 'yii\grid\ActionColumn',
                                /*'visibleButtons' => [
                                    'view' => Yii::$app->user->can('viewTask'),
                                ],*/
                                'template' => '{view}',
                                'urlCreator' => function ($action, $model, $key, $index) {
                                    if ($action === 'view') {
                                        return Yii::$app->urlManager->createUrl(['personal/task', 'id' => $model->id]);
                                    }
                                    return null;
                                },
                            ],
                        ],
                        'summaryOptions' => ['class' => 'summary mb-2'],
                        'pager' => [
                            'class' => 'yii\bootstrap4\LinkPager',
                        ]
                    ]); ?>


                </div>
                <!--.card-body-->
            </div>
            <!--.card-->
        </div>
        <!--.col-md-12-->
    </div>
    <!--.row-->
</div>
