<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Companies */
/* @var $deploymentLogs common\models\LogsCompany[] */

$this->registerJs("
    function copyApiKey" . $model->id . "() {
        var text = '" . Html::encode($model->landing_api_key) . "';
        var tempInput = document.createElement('input');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        
        var btn = $('#copy-api-key-btn-" . $model->id . "');
        var originalHtml = btn.html();
        btn.html('<i class=\"fas fa-check\"></i> Copied!');
        
        setTimeout(function() {
            btn.html(originalHtml);
        }, 2000);
    }
    
    function toggleSmtpPassword(companyId) {
        var display = $('#smtp-password-display-' + companyId);
        var btn = $('#toggle-smtp-password-' + companyId);
        var icon = btn.find('i');
        
        if (display.text() === '••••••••••••') {
            // Show password
            display.text('" . Html::encode($model->smtp_password) . "');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            // Hide password
            display.text('••••••••••••');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    }
");
?>

<div class="container-fluid">
    <div class="card card-secondary card-tabs">
        <div class="card-header p-0 pt-1">
            <ul class="nav nav-tabs" id="companyTabs-<?= $model->id ?>" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active"
                       id="info-tab-<?= $model->id ?>"
                       data-toggle="pill"
                       href="#info-<?= $model->id ?>"
                       role="tab"
                       aria-controls="info-<?= $model->id ?>"
                       aria-selected="true">
                        Company Info & Actions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       id="smtp-tab-<?= $model->id ?>"
                       data-toggle="pill"
                       href="#smtp-<?= $model->id ?>"
                       role="tab"
                       aria-controls="smtp-<?= $model->id ?>"
                       aria-selected="false">
                        SMTP Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       id="landing-tab-<?= $model->id ?>"
                       data-toggle="pill"
                       href="#landing-<?= $model->id ?>"
                       role="tab"
                       aria-controls="landing-<?= $model->id ?>"
                       aria-selected="false">
                        Landing Integration
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       id="logs-tab-<?= $model->id ?>"
                       data-toggle="pill"
                       href="#logs-<?= $model->id ?>"
                       role="tab"
                       aria-controls="logs-<?= $model->id ?>"
                       aria-selected="false">
                        Deployment Logs
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="companyTabContent-<?= $model->id ?>">
                <!-- Company Info & Actions Tab -->
                <div class="tab-pane fade show active"
                     id="info-<?= $model->id ?>"
                     role="tabpanel"
                     aria-labelledby="info-tab-<?= $model->id ?>">

                    <div class="company-info-section">
                        <h5>Company Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 200px;">ID</th>
                                <td><?= Html::encode($model->id) ?></td>
                            </tr>
                            <tr>
                                <th>Company Name</th>
                                <td><?= Html::encode($model->name) ?></td>
                            </tr>
                            <tr>
                                <th>URL</th>
                                <td>
                                    <?php if ($model->url): ?>


                                        <div class="row">
                                            <div class="col-md-6">
                                                <?= Html::a(Html::encode($model->url), $model->url, ['target' => '_blank']) ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($model->url): ?>
                                                    <?= Html::a('<i class="fas fa-external-link-alt"></i> CRM Panel',
                                                        $model->url . '/crm-panel/',
                                                        ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?= Html::tag('span', $model->getStatusName(), [
                                        'class' => 'badge ' . $model->getStatusBadgeClass()
                                    ]) ?>
                                </td>
                            </tr>
                            <?php if ($model->needsConfigUpdate()): ?>
                                <tr>
                                    <th>Config Status</th>
                                    <td>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Configuration update required
                                        </span>
                                        <small class="text-muted d-block mt-1">
                                            Domain changed from <?= Html::encode($model->previous_url) ?> to <?= Html::encode($model->url) ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Administrator</th>
                                <td>
                                    <?php if ($model->getAdministratorName()): ?>
                                        <?= Html::encode($model->getAdministratorName()) ?>
                                        <br>
                                        <small class="text-muted"><?= Html::encode($model->administrator->email) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="company-actions mt-4">
                        <h5>Actions</h5>
                        <div class="btn-group" role="group">
                            <?= Html::button(
                                '<i class="fas fa-server"></i> Deploy',
                                [
                                    'class' => 'btn btn-success company-action-btn',
                                    'data-company-id' => $model->id,
                                    'data-action' => 'deploy-crm',
                                    'data-confirm-message' => 'Start CRM deployment? This will create just the CRM system without WordPress.',
                                    'title' => 'Deploy only CRM system'
                                ]
                            ) ?>

                            <?php if ($model->needsConfigUpdate()): ?>
                                <?= Html::button(
                                    '<i class="fas fa-sync-alt"></i> Update Config',
                                    [
                                        'class' => 'btn btn-warning company-action-btn',
                                        'data-company-id' => $model->id,
                                        'data-action' => 'update-config',
                                        'data-confirm-message' => 'Update configuration files? This will apply the new domain settings.',
                                        'title' => 'Update configuration files after domain change'
                                    ]
                                ) ?>
                            <?php endif; ?>

                            <?php /*= Html::button(
                                '<i class="fas fa-rocket"></i> Full Deploy',
                                [
                                    'class' => 'btn btn-primary company-action-btn',
                                    'data-company-id' => $model->id,
                                    'data-action' => 'full-deploy',
                                    'data-confirm-message' => 'Deploy full stack (CRM + WordPress)?',
                                    'title' => 'Deploy CRM + WordPress'
                                ]
                            ) */ ?>

                            <?php if ($model->status === \common\models\Companies::STATUS_STOPPED): ?>
                                <?= Html::button(
                                    '<i class="fas fa-play-circle"></i> Start',
                                    [
                                        'class' => 'btn btn-success company-action-btn',
                                        'data-company-id' => $model->id,
                                        'data-action' => 'start',
                                        'data-confirm-message' => 'Start this company instance? This will enable nginx configs and make sites accessible.',
                                        'title' => 'Start company services'
                                    ]
                                ) ?>
                            <?php else: ?>
                                <?= Html::button(
                                    '<i class="fas fa-stop-circle"></i> Stop',
                                    [
                                        'class' => 'btn btn-warning company-action-btn',
                                        'data-company-id' => $model->id,
                                        'data-action' => 'stop',
                                        'data-confirm-message' => 'Stop this company instance? This will disable nginx configs and make sites inaccessible.',
                                        'title' => 'Stop company services'
                                    ]
                                ) ?>
                            <?php endif; ?>

                            <?= Html::button(
                                '<i class="fas fa-trash"></i> Delete',
                                [
                                    'class' => 'btn btn-danger company-action-btn',
                                    'data-company-id' => $model->id,
                                    'data-action' => 'delete',
                                    'data-confirm-message' => 'Are you sure you want to delete this company? This action cannot be undone!',
                                ]
                            ) ?>
                        </div>

                    </div>

                    <div class="mt-3">
                        <?= Html::button('Cancel', ['class' => 'btn btn-secondary cancel-action']) ?>
                    </div>
                </div>

                <!-- SMTP Settings Tab -->
                <div class="tab-pane fade"
                     id="smtp-<?= $model->id ?>"
                     role="tabpanel"
                     aria-labelledby="smtp-tab-<?= $model->id ?>">

                    <div class="company-info-section">
                        <h5>SMTP Email Configuration</h5>

                        <?php if ($model->hasSmtpSettings()): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> SMTP is configured and ready for use
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> SMTP settings are not configured. Email functionality may be limited.
                            </div>
                        <?php endif; ?>

                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 200px;">SMTP Server</th>
                                <td>
                                    <?php if ($model->smtp_server): ?>
                                        <?= Html::encode($model->smtp_server) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>SMTP Port</th>
                                <td>
                                    <?php if ($model->smtp_port): ?>
                                        <?= Html::encode($model->smtp_port) ?>
                                        <small class="text-muted">
                                            <?php if ($model->smtp_port == 587): ?>
                                                (TLS)
                                            <?php elseif ($model->smtp_port == 465): ?>
                                                (SSL)
                                            <?php elseif ($model->smtp_port == 25): ?>
                                                (Plain)
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>SMTP Login</th>
                                <td>
                                    <?php if ($model->smtp_login): ?>
                                        <?= Html::encode($model->smtp_login) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>SMTP Password</th>
                                <td>
                                    <?php if ($model->smtp_password): ?>
                                        <div class="d-flex align-items-center">
                                            <span id="smtp-password-display-<?= $model->id ?>" class="mr-2">••••••••••••</span>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    onclick="toggleSmtpPassword(<?= $model->id ?>)"
                                                    id="toggle-smtp-password-<?= $model->id ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="mt-3">
                        <?= Html::button('Cancel', ['class' => 'btn btn-secondary cancel-action']) ?>
                    </div>
                </div>

                <!-- Landing Integration Tab -->
                <div class="tab-pane fade"
                     id="landing-<?= $model->id ?>"
                     role="tabpanel"
                     aria-labelledby="landing-tab-<?= $model->id ?>">

                    <div class="landing-info-section">
                        <h5>Landing Integration</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 200px;">Landing URL</th>
                                <td>
                                    <?php if ($model->landing_url): ?>
                                        <?= Html::a(Html::encode($model->landing_url), $model->landing_url, ['target' => '_blank']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>API Key</th>
                                <td>
                                    <?php if ($model->landing_api_key): ?>
                                        <div class="d-flex align-items-center">
                                            <span class="mr-2" id="api-key-display-<?= $model->id ?>"><?= Html::encode($model->landing_api_key) ?></span>
                                            <?= Html::button('<i class="far fa-copy"></i> Copy', [
                                                'class' => 'btn btn-sm btn-secondary',
                                                'id' => 'copy-api-key-btn-' . $model->id,
                                                'title' => 'Copy to clipboard',
                                                'onclick' => 'copyApiKey' . $model->id . '(); return false;'
                                            ]) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not generated</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php /*if ($model->landing_url && ($model->status === \common\models\Companies::STATUS_RUNNING || $model->status === \common\models\Companies::STATUS_DEPLOYING)): ?>
                                <tr>
                                    <th>WordPress Admin</th>
                                    <td>
                                        <div class="alert  mb-1 py-2">
                                            <i class="fas fa-info-circle"></i> <strong>Default WordPress Credentials</strong>
                                            <small class="d-block text-muted mt-1">These are created automatically during deployment. Change them after first login.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Username:</strong> admin<br>
                                                <strong>Password:</strong> <b>wp-admin-<?= $model->id ?></b>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($model->landing_url): ?>
                                                    <?= Html::a('<i class="fas fa-external-link-alt"></i> WordPress Admin',
                                                        $model->landing_url . '/wp-admin',
                                                        ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif;*/ ?>
                        </table>
                    </div>

                    <div class="mt-3">
                        <?= Html::button('Cancel', ['class' => 'btn btn-secondary cancel-action']) ?>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div class="tab-pane fade"
                     id="logs-<?= $model->id ?>"
                     role="tabpanel"
                     aria-labelledby="logs-tab-<?= $model->id ?>">

                    <?php \yii\widgets\Pjax::begin([
                        'id' => 'pjax-logs-' . $model->id,
                        'timeout' => 10000,
                        'enablePushState' => false,
                        'enableReplaceState' => false,
                    ]); ?>

                    <?= \yii\grid\GridView::widget([
                        'dataProvider' => $logsDataProvider,
                        'tableOptions' => ['class' => 'table table-striped table-bordered table-sm company-logs'],
                        'layout' => "{summary}\n{items}\n{pager}",
                        'summaryOptions' => ['class' => 'summary mb-2 text-muted'],
                        'emptyText' => '<div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No deployment logs found for this company.
        </div>',
                        'emptyTextOptions' => ['class' => 'empty-text-wrapper'],
                        'columns' => [
                            [
                                'attribute' => 'date',
                                'label' => 'Date',
                                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                                'headerOptions' => ['style' => 'width: 150px;'],
                            ],
                            [
                                'attribute' => 'user_id',
                                'label' => 'User',
                                'format' => 'raw',
                                'value' => function ($log) {
                                    return $log->user
                                        ? \yii\helpers\Html::encode($log->user->first_name . ' ' . $log->user->last_name)
                                        : '<span class="text-muted">System</span>';
                                },
                                'headerOptions' => ['style' => 'width: 150px;'],
                            ],
                            [
                                'attribute' => 'action_type',
                                'label' => 'Action',
                                'format' => 'raw',
                                'value' => function ($log) {
                                    $badgeClass = $log->action_type === 'deploy' ? 'primary' : 'secondary';
                                    return '<span class="badge badge-' . $badgeClass . '">'
                                        . \yii\helpers\Html::encode($log->action_type) . '</span>';
                                },
                                'headerOptions' => ['style' => 'width: 100px;'],
                            ],
                            [
                                'label' => 'Details',
                                'format' => 'raw',
                                'value' => function ($log) {
                                    if (empty($log->details)) {
                                        return '<span class="text-muted">No details</span>';
                                    }

                                    $html = '<div class="log-details">';

                                    foreach ($log->details as $detail) {
                                        $html .= '<div class="mb-1 p-2 bg-light border-left border-primary" style="border-left-width: 3px !important;">';

                                        if ($detail->data_type === \common\models\LogsCompanyDetails::TYPE_JSON) {
                                            $data = json_decode($detail->data, true);
                                            if ($data) {
                                                $html .= '<div class="d-flex justify-content-between">';
                                                $html .= '<strong>' . \yii\helpers\Html::encode($data['step'] ?? 'Processing') . '</strong>';
                                                $html .= '<small class="text-muted">' . \yii\helpers\Html::encode($data['timestamp'] ?? '') . '</small>';
                                                $html .= '</div>';

                                                if (isset($data['progress']) && is_numeric($data['progress']) && $data['progress'] > 0) {
                                                    $barClass = (isset($data['error']) && $data['error']) ? 'bg-danger' : 'bg-success';
                                                    $html .= '<div class="progress mt-1" style="height: 8px;">';
                                                    $html .= '<div class="progress-bar ' . $barClass . '" style="width: ' . (int)$data['progress'] . '%"></div>';
                                                    $html .= '</div>';
                                                    $html .= '<small class="text-muted">' . (int)$data['progress'] . '% complete</small>';
                                                }

                                                if (isset($data['error']) && $data['error']) {
                                                    $html .= '<div class="text-danger mt-1">';
                                                    $html .= '<i class="fas fa-exclamation-triangle"></i> ';
                                                    $html .= \yii\helpers\Html::encode($data['message'] ?? 'Error occurred');
                                                    $html .= '</div>';
                                                }

                                                if (isset($data['output']) && $data['output']) {
                                                    $html .= '<details class="mt-1">';
                                                    $html .= '<summary class="text-info small" style="cursor: pointer;">Show Output</summary>';
                                                    $html .= '<pre class="small mt-1 p-1 bg-dark text-light" style="white-space: pre-wrap; word-break: break-word;">';
                                                    $html .= \yii\helpers\Html::encode($data['output']);
                                                    $html .= '</pre></details>';
                                                }
                                            } else {
                                                $html .= '<pre class="small mb-0" style="white-space: pre-wrap; word-break: break-word;">' . \yii\helpers\Html::encode($detail->data) . '</pre>';
                                            }
                                        } else {
                                            $html .= '<div>' . \yii\helpers\Html::encode($detail->data) . '</div>';
                                        }

                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';
                                    return $html;
                                },
                            ],
                        ],
                        'pager' => [
                            'class' => 'yii\bootstrap4\LinkPager',
                            'options' => ['class' => 'pagination pagination-sm justify-content-center'],
                        ],
                    ]) ?>

                    <?php \yii\widgets\Pjax::end(); ?>

                    <div class="mt-3">
                        <?= Html::button('Cancel', ['class' => 'btn btn-secondary cancel-action']) ?>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>