<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Companies */
/* @var $debugInfo array */

?>

<div class="crm-deployment-debug-container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6><i class="fas fa-server"></i> CRM Deployment Progress</h6>
                </div>
                <div class="card-body">
                    <div class="current-step mb-3">
                        <span id="current-step">Initializing CRM deployment...</span>
                    </div>

                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             id="progress-bar"
                             role="progressbar"
                             style="width: 0%"
                             aria-valuenow="0"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            0%
                        </div>
                    </div>

                    <div class="completion-message" id="completion-message" style="display: none;">
                        <div class="alert" id="completion-alert">
                            <div id="completion-text"></div>
                            <div id="completion-actions" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CRM Deployment Logs -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-list-alt"></i> CRM Deployment Log</h6>
                </div>
                <div class="card-body p-0">
                    <div class="deployment-logs" id="deployment-logs" style="max-height: 400px; overflow-y: auto; padding: 15px;">
                        <div class="log-entry">
                            <span class="badge badge-info"><?= date('H:i:s') ?></span>
                            <i class="fas fa-info-circle"></i>
                            <span class="ml-2">CRM deployment started for company: <?= Html::encode($model->name) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Debug Information -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6><i class="fas fa-bug"></i> Debug Information</h6>
                </div>
                <div class="card-body">
                    <div class="debug-info">
                        <h6>Company Details</h6>
                        <ul class="list-unstyled small">
                            <li><strong>ID:</strong> <?= $model->id ?></li>
                            <li><strong>Name:</strong> <?= Html::encode($model->name) ?></li>
                            <li><strong>Domain:</strong> <?= Html::encode($model->url ?: 'Auto-generated') ?></li>
                            <li><strong>Status:</strong> <span class="badge <?= $model->getStatusBadgeClass() ?>"><?= $model->getStatusName() ?></span></li>
                        </ul>

                        <?php if (!empty($debugInfo)): ?>
                            <h6 class="mt-3">Deployment Info</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Log ID:</strong> <?= $debugInfo['log_id'] ?? 'N/A' ?></li>
                                <li><strong>Started:</strong> <?= $debugInfo['timestamp'] ?? 'N/A' ?></li>
                                <?php if (!empty($debugInfo['script_path'])): ?>
                                    <li><strong>Script:</strong> <code><?= basename($debugInfo['script_path']) ?></code></li>
                                <?php endif; ?>
                                <?php if (!empty($debugInfo['log_path'])): ?>
                                    <li><strong>Log File:</strong> <code><?= basename($debugInfo['log_path']) ?></code></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>

                        <h6 class="mt-3">Expected CRM Components</h6>
                        <ul class="list-unstyled small">
                            <li><i class="fas fa-check-circle text-muted"></i> Project directories</li>
                            <li><i class="fas fa-check-circle text-muted"></i> Database configuration</li>
                            <li><i class="fas fa-check-circle text-muted"></i> Composer dependencies</li>
                            <li><i class="fas fa-check-circle text-muted"></i> Database migrations</li>
                            <li><i class="fas fa-check-circle text-muted"></i> Nginx configuration</li>
                            <li><i class="fas fa-check-circle text-muted"></i> Admin user</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- CRM Templates Info -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h6><i class="fas fa-folder"></i> Template Requirements</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <p><strong>CRM Template Path:</strong></p>
                        <code>/var/www/templates/crm-base/</code>

                        <p class="mt-2"><strong>Required Files:</strong></p>
                        <ul class="list-unstyled">
                            <li>✓ composer.json</li>
                            <li>✓ yii console script</li>
                            <li>✓ Common, frontend, backend modules</li>
                            <li>✓ Migration files</li>
                        </ul>

                        <p class="mt-2"><strong>Auto-created:</strong></p>
                        <ul class="list-unstyled">
                            <li>→ Database config</li>
                            <li>→ Nginx virtual host</li>
                            <li>→ Runtime directories</li>
                            <li>→ Admin user account</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const companyId = <?= $model->id ?>;
        let isCompleted = false;
        let lastDetailId = 0;

        // For SSE support check
        if (typeof(EventSource) !== "undefined") {
            startSSEConnection();
        } else {
            // Fallback to AJAX polling
            startAjaxPolling();
        }

        function startSSEConnection() {
            const eventSource = new EventSource('<?= \yii\helpers\Url::to(['deployment-stream']) ?>?id=' + companyId);

            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleDeploymentUpdate(data);

                if (data.status === 'completed' || data.status === 'failed') {
                    isCompleted = true;
                    eventSource.close();
                }
            };

            eventSource.onerror = function(event) {
                console.error('SSE connection error, falling back to AJAX polling');
                eventSource.close();

                if (!isCompleted) {
                    setTimeout(startAjaxPolling, 2000);
                }
            };
        }

        function startAjaxPolling() {
            if (isCompleted) return;

            $.ajax({
                url: '<?= \yii\helpers\Url::to(['get-live-progress']) ?>',
                data: {
                    companyId: companyId,
                    lastDetailId: lastDetailId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.new_details) {
                        response.new_details.forEach(function(detail) {
                            handleDeploymentUpdate(detail);
                            lastDetailId = Math.max(lastDetailId, detail.id);
                        });

                        if (response.deployment_status.is_completed || response.deployment_status.is_failed) {
                            isCompleted = true;
                            return;
                        }
                    }

                    // Continue polling
                    setTimeout(startAjaxPolling, 2000);
                },
                error: function() {
                    // Retry after longer delay on error
                    if (!isCompleted) {
                        setTimeout(startAjaxPolling, 5000);
                    }
                }
            });
        }

        function handleDeploymentUpdate(data) {
            if (data.progress !== undefined && data.progress !== null) {
                updateProgress(data.progress, data.step || '');
            }

            if (data.step) {
                const logType = data.error ? 'error' : 'success';
                addLogEntry(logType, data.step, data.output || data.message, data.timestamp);
            }

            if (data.status === 'completed' && !isCompleted) {
                isCompleted = true;
                showCompletion(true, 'CRM deployment completed successfully!');

                // Redirect after 3 seconds
                setTimeout(function() {
                    window.location.href = '<?= \yii\helpers\Url::to(['index']) ?>';
                }, 3000);

            } else if ((data.status === 'failed' || data.error) && !isCompleted) {
                isCompleted = true;
                showCompletion(false, data.message || 'CRM deployment failed');
            }
        }

        function updateProgress(progress, step) {
            if (window.debugDeploymentFunctions && window.debugDeploymentFunctions.updateProgress) {
                window.debugDeploymentFunctions.updateProgress(progress, step);
            }
        }

        function addLogEntry(type, message, output, timestamp) {
            if (window.debugDeploymentFunctions && window.debugDeploymentFunctions.addLogEntry) {
                window.debugDeploymentFunctions.addLogEntry(type, message, output, timestamp);
            }
        }

        function showCompletion(success, message) {
            if (window.debugDeploymentFunctions && window.debugDeploymentFunctions.showCompletion) {
                window.debugDeploymentFunctions.showCompletion(success, message);
            }
        }
    });
</script>