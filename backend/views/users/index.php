<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $tabsData array */

$this->title = 'Users';
$this->params['breadcrumbs'][] = $this->title;

$css = "
.table-danger-soft {
    background-color: #f8d7da !important;
    color: #721c24;
}
.table-danger-soft:hover {
    background-color: #f5c6cb !important;
}
";

$this->registerCss($css);

// Page-specific JS for user actions
$script = "

function getActionUrl(action) {
    var url = '';
    if (action === 'view') {
        url = '" . Url::to(['view']) . "';       
    } else if (action === 'update') {
        url = '" . Url::to(['update']) . "';       
    } else if (action === 'change-password') {
        url = '" . Url::to(['change-password']) . "';       
    }
    return url;
}

function showActionDetails(event, action, userId) {
    event.preventDefault();
    event.stopPropagation();
    
    var \$clickedRow = $(event.target).closest('tr');
    var existingActionRow = \$clickedRow.next('.action-details-row');    
   
    if (existingActionRow.length) {
        var currentAction = existingActionRow.data('current-action');        
      
        if (currentAction === action) {
            existingActionRow.find('.action-details').slideUp(500, function() {
                existingActionRow.remove();
            });
            return;
        }        
       
        var url = getActionUrl(action);
        var contentDiv = existingActionRow.find('.content');
        
        existingActionRow.data('current-action', action);        
        
        contentDiv.fadeOut(200, function() {
            $(this).html('Loading...');
            $(this).fadeIn(200);            
         
            if (action === 'change-password') {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: { id: userId },             
                    success: function(response) {
                        response = JSON.parse(response);
                        if (typeof response.tpl != 'undefined') {
                            contentDiv.fadeOut(200, function() {
                                $(this).html(response.tpl);
                                $(this).fadeIn(300);
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        contentDiv.fadeOut(200, function() {
                            $(this).html('Failed to load data');
                            $(this).fadeIn(200);
                        });
                    }
                });
                return;
            }
            
            $.ajax({
                url: url,
                type: 'GET',
                data: { id: userId },             
                success: function(response) {
                    response = JSON.parse(response);
                    if (typeof response.tpl != 'undefined') {
                        contentDiv.fadeOut(200, function() {
                            $(this).html(response.tpl);
                            $(this).fadeIn(300);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    contentDiv.fadeOut(200, function() {
                        $(this).html('Failed to load data');
                        $(this).fadeIn(200);
                    });
                }
            });
        });
        
        return;
    }    
   
    $('.action-details-row').each(function() {
        var \$this = $(this);
        \$this.find('.action-details').slideUp(700, function() {
            \$this.remove();
        });
    });    
   
    var actionRow = $('<tr class=\"action-details-row\"><td colspan=\"' + \$clickedRow.find('td').length + '\"><div class=\"action-details\"><div class=\"content\">Loading...</div></div></td></tr>');
    actionRow.data('current-action', action);
    
    \$clickedRow.after(actionRow);
    
    var url = getActionUrl(action);
    
    $.ajax({
        url: url,
        type: 'GET',
        data: { id: userId },    
        success: function(response) {
            response = JSON.parse(response);
            if (typeof response.tpl != 'undefined') {
                actionRow.find('.content').html(response.tpl);
                actionRow.find('.action-details').hide().slideDown(700);
            }
        },
        error: function(xhr, status, error) {          
            actionRow.find('.content').html('Failed to load data');
            actionRow.find('.action-details').hide().slideDown(700);
        }
    });
}

$(document).on('click', '.update-employee-send', function (e){
    e.preventDefault();
    
    let button = $(this);
    let form = button.closest('form');
    let action = form.prop('action');
    let data = form.serialize();    
   
    if (form.find('.has-error').length) {
        return false;
    }    
   
    button.prop('disabled', true).text('Saving...');
    
    $.ajax({
        type: 'POST',
        url: action,
        data: data,
        success: function (response) {
            response = JSON.parse(response);
            if (typeof response.success != 'undefined') {
                if(response.success == true) {                 
                    toastr.success(response.message);                    
                   
                    $('.action-details-row').find('.action-details').slideUp(700, function() {
                        $('.action-details-row').remove();
                    });
                    
                    // Reload page to update table
                    location.reload();
                } else {                    
                    if (typeof response.tpl != 'undefined') {
                        button.closest('.content').html(response.tpl);
                    }
                }
            }
        },
        error: function() {
            toastr.error('An error occurred while saving');
        },
        complete: function() {
            button.prop('disabled', false).text('Save');
        }
    });
});

$(document).on('submit', '#change-password-form', function (e){
    e.preventDefault();
    
    let form = $(this);
    let button = form.find('.change-password-submit');
    let action = form.prop('action');
    let data = form.serialize();    
   
    button.prop('disabled', true).text('Changing...');
    
    $.ajax({
        type: 'POST',
        url: action,
        data: data,
        success: function (response) {
            response = JSON.parse(response);
            if (typeof response.success != 'undefined') {
                if(response.success == true) {                
                    toastr.success(response.message);                    
                   
                    $('.action-details-row').find('.action-details').slideUp(700, function() {
                        $('.action-details-row').remove();
                    });
                } else {                   
                    if (typeof response.tpl != 'undefined') {
                        button.closest('.content').html(response.tpl);
                    } else if (response.message) {
                        toastr.error(response.message);
                    }
                }
            }
        },
        error: function() {
            toastr.error('An error occurred while changing password');
        },
        complete: function() {          
            if (button.length) {
                button.prop('disabled', false).text('Change Password');
            }
        }
    });
});

$(document).on('click', '.cancel-action', function (e){
    e.preventDefault();
    $(this).closest('.action-details-row').find('.action-details').slideUp(700, function() {
        $(this).closest('.action-details-row').remove();
    });
});
";

$this->registerJs($script, \yii\web\View::POS_END);
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="card card-secondary card-tabs">
                        <div class="card-header p-0 pt-1">
                            <ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
                                <?php foreach ($tabsData as $tab): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $tab['active'] ? 'active' : '' ?>"
                                        id="<?= $tab['id'] ?>-tab"
                                        data-toggle="pill"
                                        href="#<?= $tab['id'] ?>"
                                        role="tab"
                                        aria-controls="<?= $tab['id'] ?>"
                                        aria-selected="<?= $tab['active'] ? 'true' : 'false' ?>">
                                        <?= $tab['label'] ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="custom-tabs-one-tabContent">
                                <?php foreach ($tabsData as $tab): ?>
                                    <div
                                        <a class="tab-pane fade <?= $tab['active'] ? 'show active' : '' ?>"
                                            id="<?= $tab['id'] ?>"
                                            role="tabpanel"
                                            aria-labelledby="<?= $tab['id'] ?>-tab">
                                        <div class="row mb-2">
                                            <div class="col-md-12">
                                                <?php if (Yii::$app->user->can('createUser')): ?>
                                                    <?= Html::a('Create ' . $tab['label'], ['create','role' => $tab['role']], ['class' => 'btn btn-success']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?= GridView::widget([
                                            'dataProvider' => $tab['dataProvider'],
                                            'tableOptions' => ['class' => 'table table-striped table-bordered'],
                                            'columns' => [
                                                ['class' => 'yii\grid\SerialColumn'],
                                                'email:email',
                                                'first_name',
                                                'last_name',
                                                'phone_number',
                                                [
                                                    'attribute' => 'city',
                                                    'value' => function($model) use ($countries) {
                                                        $parts = array_filter([
                                                            $model->city,
                                                            $model->state,
                                                        ]);

                                                        // Add country name instead of code
                                                        if ($model->country) {
                                                            $countryName = $countries[$model->country] ?? $model->country;
                                                            $parts[] = $countryName;
                                                        }

                                                        return implode(', ', $parts);
                                                    },
                                                    'label' => 'Location',
                                                ],
                                                [
                                                    'attribute' => 'substatus',
                                                    'value' => function($model) {
                                                        return $model->getSubstatusLabel();
                                                    },
                                                    'label' => 'Status',
                                                    'visible' => $tab['role'] === 'employee',
                                                    'contentOptions' => function($model) {
                                                        switch ($model->substatus) {
                                                            case \backend\models\User::SUBSTATUS_ACTIVE_EMPLOYEE: // 8 - Active Employee
                                                                return ['class' => 'table-success'];
                                                            case \backend\models\User::SUBSTATUS_CONTRACT_SENT: // 5 - Contract Sent
                                                                return ['class' => 'table-warning'];
                                                            case \backend\models\User::SUBSTATUS_CONTRACT_REFUSED: // 9 - Contract Refused
                                                                return ['class' => 'table-danger-soft'];
                                                            default:
                                                                return [];
                                                        }
                                                    },
                                                ],
                                                [
                                                    'attribute' => 'created_at',
                                                    'format' => ['date', 'php:Y-m-d'],
                                                ],
                                                [
                                                    'class' => 'yii\grid\ActionColumn',
                                                    'visibleButtons' => [
                                                        'view' => Yii::$app->user->can('viewUser'),
                                                        'update' => Yii::$app->user->can('updateUser'),
                                                        'delete' => Yii::$app->user->can('deleteUser'),
                                                    ],
                                                    'template' => '{view} {update} {change-password} {delete}',
                                                    'buttons' => [
                                                        'view' => function ($url, $model, $key) {
                                                            return Html::a(
                                                                '<svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1.125em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M573 241C518 136 411 64 288 64S58 136 3 241a32 32 0 000 30c55 105 162 177 285 177s230-72 285-177a32 32 0 000-30zM288 400a144 144 0 11144-144 144 144 0 01-144 144zm0-240a95 95 0 00-25 4 48 48 0 01-67 67 96 96 0 1092-71z"></path></svg>',
                                                                '#',
                                                                [
                                                                    'title' => Yii::t('app', 'View'),
                                                                    'onclick' => 'showActionDetails(event, "view", ' . $model->id . '); return false;',
                                                                    'data-pjax' => '0',
                                                                ]
                                                            );
                                                        },
                                                        'update' => function ($url, $model, $key) {
                                                            return Html::a(
                                                                '<svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M498 142l-46 46c-5 5-13 5-17 0L324 77c-5-5-5-12 0-17l46-46c19-19 49-19 68 0l60 60c19 19 19 49 0 68zm-214-42L22 362 0 484c-3 16 12 30 28 28l122-22 262-262c5-5 5-13 0-17L301 100c-4-5-12-5-17 0zM124 340c-5-6-5-14 0-20l154-154c6-5 14-5 20 0s5 14 0 20L144 340c-6 5-14 5-20 0zm-36 84h48v36l-64 12-32-31 12-65h36v48z"></path></svg>',
                                                                '#',
                                                                [
                                                                    'title' => Yii::t('app', 'Update'),
                                                                    'onclick' => 'showActionDetails(event, "update", ' . $model->id . '); return false;',
                                                                    'data-pjax' => '0',
                                                                ]
                                                            );
                                                        },
                                                        'change-password' => function ($url, $model, $key) {
                                                            if (!Yii::$app->user->can('super-administrator')) {
                                                                return '';
                                                            }

                                                            return Html::a(
                                                                '<svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1.125em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M512 176.001C512 273.203 433.202 352 336 352c-11.22 0-22.19-1.062-32.827-3.069l-24.012 27.014A23.999 23.999 0 01261.223 384H224v40c0 13.255-10.745 24-24 24h-40v40c0 13.255-10.745 24-24 24H24c-13.255 0-24-10.745-24-24v-78.059c0-6.365 2.529-12.47 7.029-16.971l161.802-161.802C163.108 213.814 160 195.271 160 176.001 160 78.798 238.797.001 335.999 0 433.488-.001 512 78.511 512 176.001zM336 128c0 26.51 21.49 48 48 48s48-21.49 48-48-21.49-48-48-48-48 21.49-48 48z"></path></svg>',
                                                                '#',
                                                                [
                                                                    'title' => Yii::t('app', 'Change Password'),
                                                                    'onclick' => 'showActionDetails(event, "change-password", ' . $model->id . '); return false;',
                                                                    'data-pjax' => '0',
                                                                ]
                                                            );
                                                        },
                                                        'delete' => function ($url, $model, $key) {
                                                            return Html::a(
                                                                '<svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:.875em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M32 464a48 48 0 0048 48h288a48 48 0 0048-48V128H32zm272-256a16 16 0 0132 0v224a16 16 0 01-32 0zm-96 0a16 16 0 0132 0v224a16 16 0 01-32 0zm-96 0a16 16 0 0132 0v224a16 16 0 01-32 0zM432 32H312l-9-19a24 24 0 00-22-13H167a24 24 0 00-22 13l-9 19H16A16 16 0 000 48v32a16 16 0 0016 16h416a16 16 0 0016-16V48a16 16 0 00-16-16z"></path></svg>',
                                                                $url,
                                                                [
                                                                    'title' => Yii::t('app', 'Delete'),
                                                                    'data-confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                                                                    'data-method' => 'post',
                                                                ]
                                                            );
                                                        },
                                                    ],
                                                ],
                                            ],
                                            'summaryOptions' => ['class' => 'summary mb-2'],
                                            'pager' => [
                                                'class' => 'yii\bootstrap4\LinkPager',
                                            ]
                                        ]) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>