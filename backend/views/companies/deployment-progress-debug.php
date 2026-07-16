<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Companies */
/* @var $debugInfo array */
?>

<div class="deployment-debug-container">
    <div class="alert alert-info">
        <h5><i class="fas fa-bug"></i> DEBUG MODE: Deployment Progress</h5>
        <p>Company: <strong><?= Html::encode($model->name) ?></strong> (ID: <?= $model->id ?>)</p>
        <p>Status: <strong><?= $model->getStatusName() ?></strong></p>
    </div>

    <!-- Debug Information -->
    <div class="card mb-3">
        <div class="card-header">
            <h6><i class="fas fa-info-circle"></i> Debug Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>PHP Path:</strong> <?= Html::encode($debugInfo['php_path'] ?? 'N/A') ?><br>
                    <strong>Console Path:</strong> <?= Html::encode($debugInfo['console_path'] ?? 'N/A') ?><br>
                    <strong>Console Exists:</strong> <?= ($debugInfo['console_exists'] ?? false) ? 'Yes' : 'No' ?><br>
                    <strong>Log Path:</strong> <?= Html::encode($debugInfo['log_path'] ?? 'N/A') ?>
                </div>
                <div class="col-md-6">
                    <strong>PHP Test:</strong> Code <?= $debugInfo['php_test']['return_code'] ?? 'N/A' ?><br>
                    <strong>Console Test:</strong> Code <?= $debugInfo['console_test']['return_code'] ?? 'N/A' ?><br>
                    <strong>Log Dir Writable:</strong> <?= ($debugInfo['log_dir_writable'] ?? false) ? 'Yes' : 'No' ?><br>
                    <strong>Script Created:</strong> <?= ($debugInfo['script_created'] ?? false) ? 'Yes' : 'No' ?>
                </div>
            </div>

            <?php if (isset($debugInfo['error'])): ?>
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong> <?= Html::encode($debugInfo['error']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-3">
        <div class="card-header">
            <h6><i class="fas fa-progress"></i> Deployment Progress</h6>
        </div>
        <div class="card-body">
            <div class="current-step mb-3" id="current-step">Connecting to deployment stream...</div>

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
                <h5 id="completion-text"></h5>
                <div id="completion-actions"></div>
            </div>
        </div>
    </div>

    <!-- Live Logs -->
    <div class="card">
        <div class="card-header">
            <h6><i class="fas fa-terminal"></i> Real-time Deployment Logs</h6>
            <div class="card-tools">
                <button id="toggle-raw-logs" class="btn btn-sm btn-outline-secondary">Show Raw Debug</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="deployment-logs" id="deployment-logs" style="background: #f8f9fa; padding: 15px; max-height: 400px; overflow-y: auto;">
                <div class="log-entry">
                    <span class="badge badge-info"><?= date('H:i:s') ?></span>
                    Deployment stream connecting...
                </div>
            </div>
        </div>
    </div>

    <!-- Raw Debug Info (Hidden by default) -->
    <div class="card mt-3" id="raw-debug" style="display: none;">
        <div class="card-header">
            <h6><i class="fas fa-code"></i> Raw Debug Information</h6>
        </div>
        <div class="card-body">
            <pre style="max-height: 300px; overflow-y: auto; font-size: 12px;"><?= Html::encode(json_encode($debugInfo, JSON_PRETTY_PRINT)) ?></pre>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const companyId = <?= $model->id ?>;
        let isCompleted = false;
        let connectionAttempts = 0;
        let maxAttempts = 5;

        // Toggle raw debug info
        document.getElementById('toggle-raw-logs').addEventListener('click', function() {
            const rawDebug = document.getElementById('raw-debug');
            if (rawDebug.style.display === 'none') {
                rawDebug.style.display = 'block';
                this.textContent = 'Hide Raw Debug';
            } else {
                rawDebug.style.display = 'none';
                this.textContent = 'Show Raw Debug';
            }
        });

        function connectSSE() {
            connectionAttempts++;
            addLogEntry('info', `Attempting SSE connection #${connectionAttempts}...`);

            const eventSource = new EventSource('<?= Url::to(['deployment-stream']) ?>?id=' + companyId + '&debug=1');

            eventSource.onopen = function(event) {
                addLogEntry('success', 'SSE connection established');
            };

            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('SSE Data:', data); // Debug log

                    // Handle heartbeat
                    if (data.heartbeat) {
                        addLogEntry('info', `Heartbeat #${data.iteration} - Memory: ${data.memory_usage}`);
                        return;
                    }

                    // Handle connection message
                    if (data.status === 'connected') {
                        addLogEntry('success', `Connected to deployment stream (Server: ${data.server_type})`);
                        return;
                    }

                    // Update progress
                    if (data.progress !== undefined && data.progress !== null) {
                        updateProgress(data.progress, data.step || '');
                    }

                    // Add log entries
                    if (data.step) {
                        addLogEntry(data.error ? 'error' : 'success', data.step, data.output, data.timestamp);
                    }

                    // Handle completion
                    if (data.status === 'completed' && !isCompleted) {
                        isCompleted = true;
                        eventSource.close();
                        showCompletion(true, 'Deployment completed successfully!');
                    } else if (data.status === 'failed' && !isCompleted) {
                        isCompleted = true;
                        eventSource.close();
                        showCompletion(false, data.message || 'Deployment failed');
                    } else if (data.status === 'timeout') {
                        addLogEntry('warning', data.message || 'Deployment timeout');
                    }

                } catch (e) {
                    console.error('Error parsing SSE data:', e);
                    addLogEntry('error', 'Error parsing server response: ' + e.message);
                }
            };

            eventSource.onerror = function(event) {
                console.error('SSE Error:', event);
                addLogEntry('error', `SSE connection error (attempt ${connectionAttempts})`);

                eventSource.close();

                if (!isCompleted && connectionAttempts < maxAttempts) {
                    addLogEntry('info', `Retrying connection in 3 seconds...`);
                    setTimeout(connectSSE, 3000);
                } else {
                    addLogEntry('error', 'Maximum connection attempts reached. Please refresh page.');
                    showCompletion(false, 'Connection failed after multiple attempts');
                }
            };
        }

        function updateProgress(progress, step) {
            const progressBar = document.getElementById('progress-bar');
            const currentStep = document.getElementById('current-step');

            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
            progressBar.textContent = progress + '%';

            if (step) {
                currentStep.textContent = step;
            }

            if (progress >= 100) {
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
            }
        }

        function addLogEntry(type, message, output = null, timestamp = null) {
            const logsContainer = document.getElementById('deployment-logs');
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry mb-2';

            const timeStr = timestamp ? new Date(timestamp).toLocaleTimeString() : new Date().toLocaleTimeString();

            let badgeClass = 'badge-secondary';
            let icon = 'fas fa-info-circle';

            switch(type) {
                case 'success':
                    badgeClass = 'badge-success';
                    icon = 'fas fa-check-circle';
                    break;
                case 'error':
                    badgeClass = 'badge-danger';
                    icon = 'fas fa-exclamation-triangle';
                    break;
                case 'warning':
                    badgeClass = 'badge-warning';
                    icon = 'fas fa-exclamation';
                    break;
                case 'info':
                    badgeClass = 'badge-info';
                    icon = 'fas fa-info-circle';
                    break;
            }

            logEntry.innerHTML = `
            <span class="badge ${badgeClass}">${timeStr}</span>
            <i class="${icon}"></i>
            <span class="ml-2">${escapeHtml(message)}</span>
            ${output ? '<pre class="mt-2 small text-muted" style="white-space: pre-wrap;">' + escapeHtml(output) + '</pre>' : ''}
        `;

            logsContainer.appendChild(logEntry);
            logsContainer.scrollTop = logsContainer.scrollHeight;
        }

        function showCompletion(success, message) {
            const completionDiv = document.getElementById('completion-message');
            const completionText = document.getElementById('completion-text');
            const completionActions = document.getElementById('completion-actions');

            completionDiv.className = 'completion-message alert ' + (success ? 'alert-success' : 'alert-danger');

            completionText.innerHTML = `<i class="fas fa-${success ? 'check' : 'times'}-circle"></i> ${message}`;

            if (success) {
                completionActions.innerHTML = `
                <a href="<?= Url::to(['index']) ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Companies List
                </a>
            `;
            } else {
                completionActions.innerHTML = `
                <a href="<?= Url::to(['index']) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Companies List
                </a>
                <button class="btn btn-warning ml-2" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Retry Deployment
                </button>
            `;
            }

            completionDiv.style.display = 'block';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Start SSE connection
        connectSSE();
    });
</script>

<style>
    .log-entry {
        padding: 5px;
        border-bottom: 1px solid #eee;
    }
    .log-entry:last-child {
        border-bottom: none;
    }
    .deployment-debug-container .card-tools {
        float: right;
    }
</style>