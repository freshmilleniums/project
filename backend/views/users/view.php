<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model backend\models\User */
/* @var $countries array */

$this->title = $model->getFullName();
$this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

// Check if user has employee role
$isEmployee = $model->role === 'employee';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <?= DetailView::widget([
                        'model' => $model,
                        'options' => ['class' => 'table table-bordered detail-view'],
                        'attributes' => [
                            [
                                'attribute' => 'sequential_number',
                                'visible' => $isEmployee,
                            ],
                            'email:email',
                            'first_name',
                            'last_name',
                            'phone_number',
                            'home_phone',
                            'position_title',
                            'address',
                            'city',
                            'state',
                            [
                                'attribute' => 'country',
                                'value' => function($model) use ($countries) {
                                    if (!$model->country) {
                                        return null;
                                    }
                                    return $countries[$model->country] ?? $model->country;
                                },
                            ],
                            'zip_code',
                            'hr_source',
                            [
                                'attribute' => 'substatus',
                                'value' => function($model) {
                                    return $model->getSubstatusLabel();
                                },
                                'label' => 'Status',
                                'visible' => $isEmployee,
                            ],
                            [
                                'attribute' => 'administrator_id',
                                'value' => function($model) {
                                    return $model->administrator ? $model->administrator->getFullName() : 'Not assigned';
                                },
                                'label' => 'Administrator',
                                'visible' => $isEmployee,
                            ],
                            [
                                'attribute' => 'is_online',
                                'value' => function($model) {
                                    return $model->is_online ? 'Online' : 'Offline';
                                },
                            ],
                            [
                                'attribute' => 'last_activity',
                                'value' => function($model) {
                                    if (!$model->last_activity) {
                                        return 'Never';
                                    }
                                    return date('Y-m-d H:i:s', $model->last_activity);
                                },
                                'label' => 'Last Activity',
                            ],
                            [
                                'attribute' => 'created_at',
                                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                            ],
                            [
                                'attribute' => 'updated_at',
                                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                            ],
                        ],
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>