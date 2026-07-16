<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model backend\models\Companies */

$this->title = 'Deployment Progress: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Companies', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Register CSRF meta tag for AJAX requests
$this->registerMetaTag(['name' => 'csrf-token', 'content' => Yii::$app->request->csrfToken], 'csrf-token');
$this->registerMetaTag(['name' => 'csrf-param', 'content' => Yii::$app->request->csrfParam], 'csrf-param');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="card-title mb-0">
                         CRM Deployment Progress -
                        <b>Company: <?= Html::encode($model->name) ?></b>
                    </h4>
                </div>

                <div class="card-body p-3">
                    <!-- Company Information Row -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Company Information</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Name:</strong> <?= Html::encode($model->name) ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>URL:</strong> <?= Html::encode($model->url) ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Status:</strong>
                                            <span class="badge <?= $model->getStatusBadgeClass() ?>">
                                                <?= $model->getStatusName() ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress mt-3" style="height: 25px;">
                        <div id="liveProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 5%">5%</div>
                    </div>

                    <!-- Status Alert -->
                    <div id="deploymentStatusLive" class="alert alert-info mt-2">
                        <i class="fas fa-satellite-dish"></i> Initializing deployment...
                    </div>

                    <!-- Logs -->
                    <div id="deploymentLogsLive" class="mt-3" style="max-height: 400px; overflow-y: auto; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px;">
                        <div style="color: #666;">[<?= date('H:i:s') ?>] Starting deployment monitoring...</div>
                    </div>
                </div>

                <div class="card-footer" id="actionButtons" style="display: none;">
                    <!-- Success buttons -->
                    <div id="successButtons" style="display: none;">
                        <a href="<?= Url::to(['index']) ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Companies
                        </a>
                        <a href="#" id="crmLink" class="btn btn-success" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Open CRM
                        </a>
                    </div>

                    <!-- Error buttons -->
                    <div id="errorButtons" style="display: none;">
                        <a href="<?= Url::to(['index']) ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Companies
                        </a>
                    </div>

                    <!-- Timeout buttons -->
                    <div id="timeoutButtons" style="display: none;">
                        <a href="<?= Url::to(['index']) ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Companies
                        </a>
                        <button onclick="checkDeploymentStatus()" class="btn btn-info">
                            <i class="fas fa-sync-alt"></i> Check Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let eventSource = null;
    let isMonitoring = false;
    let deploymentTimeout = null;
    let connectionAttempts = 0;
    let maxConnectionAttempts = 5;
    let deploymentFinished = false;

    // Company data
    const companyId = <?= $model->id ?>;
    const companyUrl = '<?= Html::encode($model->url) ?>';

    document.addEventListener('DOMContentLoaded', function() {
        initializeDeploymentMonitoring();
    });

    function initializeDeploymentMonitoring() {
        if (isMonitoring) return;

        isMonitoring = true;
        updateStatus('Starting deployment...', 'connecting');

        startFullDeployment();
    }

    function startFullDeployment() {
        addLogToPage('info', 'Sending deployment request...');

        // Get CSRF token from meta tags
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var csrfParam = $('meta[name="csrf-param"]').attr('content');

        var requestData = { id: companyId };
        if (csrfParam && csrfToken) {
            requestData[csrfParam] = csrfToken;
        }

        $.ajax({
            url: '<?= Url::to(['deploy-crm']) ?>' + '?id=' + companyId,
            method: 'POST',
            data: requestData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                console.log('Deploy response:', response);

                if (response.success) {
                    addLogToPage('success', 'Deployment started successfully!');

                    if (response.show_progress) {
                        updateStatus('Deployment initiated, connecting to monitoring...', 'connecting');

                        setTimeout(function() {
                            startSSEMonitoring();
                        }, 1000);
                    } else {
                        updateStatus('Deployment started successfully', 'connected');
                        addLogToPage('info', 'Deployment running in background...');
                    }

                } else {
                    addLogToPage('error', 'Failed to start deployment: ' + response.message);
                    updateStatus('Deployment failed to start', 'disconnected');
                    showErrorButtons();
                }
            },
            error: function(xhr, status, error) {
                console.error('Deploy AJAX Error:', xhr.responseText);
                addLogToPage('error', 'Deployment request failed: ' + error);
                updateStatus('Failed to initiate deployment', 'disconnected');
                showErrorButtons();
            }
        });
    }

    function startSSEMonitoring() {
        try {
            // Set deployment timeout (10 minutes)
            deploymentTimeout = setTimeout(function() {
                handleTimeout();
            }, 600000);

            const sseUrl = '/crm-panel/companies/deployment-stream?id=' + companyId;

            eventSource = new EventSource(sseUrl);

            eventSource.onopen = function() {
                updateStatus('Connected to deployment stream', 'connected');
                connectionAttempts = 0;
                addLogToPage('success', 'Connected to deployment monitoring');
            };

            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('📨 SSE Data received:', data);

                    if (data.progress !== undefined) {
                        const progressBar = $('#liveProgressBar');
                        progressBar.css('width', data.progress + '%');
                        progressBar.text(data.progress + '%');

                        if (data.progress >= 100) {
                            progressBar.removeClass('progress-bar-animated').addClass('bg-success');
                        }
                    }

                    if (data.status === 'not_deploying' || data.status === 'completed' || data.status === 'failed') {
                        deploymentFinished = true;
                        eventSource.close();
                        clearTimeout(deploymentTimeout);
                        console.log('🛑 SSE stopped - deployment finished with status:', data.status);

                        if (data.status === 'completed') {
                            $('#deploymentStatusLive').removeClass().addClass('alert alert-success');
                            $('#deploymentStatusLive').html('<i class="fas fa-check-circle"></i> ✅ Deployment completed successfully!');
                            addLogToPage('success', 'Deployment completed successfully!');
                            showSuccessButtons();
                        } else if (data.status === 'failed') {
                            $('#deploymentStatusLive').removeClass().addClass('alert alert-danger');
                            $('#deploymentStatusLive').html('<i class="fas fa-exclamation-triangle"></i> ❌ Deployment failed');
                            addLogToPage('error', 'Deployment failed: ' + (data.message || 'Unknown error'));
                            showErrorButtons();
                        } else if (data.status === 'not_deploying') {
                            $('#deploymentStatusLive').removeClass().addClass('alert alert-warning');
                            $('#deploymentStatusLive').html('<i class="fas fa-info-circle"></i> ⚠️ Deployment not running');
                            addLogToPage('warning', 'Company is not in deploying status');
                            showErrorButtons();
                        }

                        return;
                    }

                    if (data.step) {
                        const alertType = data.error ? 'alert-danger' : 'alert-info';
                        $('#deploymentStatusLive').removeClass('alert-info alert-success alert-danger alert-warning').addClass(alertType);
                        $('#deploymentStatusLive').html('<i class="fas fa-cog fa-spin"></i> ' + data.step);

                        addLogToPage(data.error ? 'error' : 'info', data.step);
                    }

                    if (data.progress >= 100 && !data.error) {
                        setTimeout(function() {
                            if (!deploymentFinished) {
                                deploymentFinished = true;
                                eventSource.close();
                                clearTimeout(deploymentTimeout);
                                console.log('🛑 SSE stopped - 100% progress reached');
                            }
                        }, 2000);
                    }

                } catch (e) {
                    console.error('❌ Error parsing SSE data:', e);
                    addLogToPage('error', 'Failed to parse progress data');
                }
            };

            eventSource.onerror = function(event) {
                console.warn('⚠️ SSE connection error');

                if (deploymentFinished) {
                    console.log('🛑 Deployment finished, not reconnecting');
                    eventSource.close();
                    return;
                }

                connectionAttempts++;
                if (connectionAttempts > maxConnectionAttempts) {
                    deploymentFinished = true;
                    eventSource.close();
                    clearTimeout(deploymentTimeout);
                    console.log('🛑 Max reconnection attempts reached, stopping SSE');
                    updateStatus('Connection failed after multiple attempts', 'disconnected');
                    addLogToPage('error', 'Connection failed after ' + maxConnectionAttempts + ' attempts');
                    showTimeoutButtons();
                    return;
                }

                updateStatus('Connection issue - retrying (' + connectionAttempts + '/' + maxConnectionAttempts + ')...', 'connecting');
                addLogToPage('warning', 'Connection issue, attempting to reconnect... (' + connectionAttempts + '/' + maxConnectionAttempts + ')');
            };

        } catch (error) {
            console.error('Failed to start SSE:', error);
            addLogToPage('error', 'Failed to start real-time monitoring: ' + error.message);
            showErrorButtons();
        }
    }

    function updateStatus(message, type) {
        const statusMessage = document.getElementById('deploymentStatusLive');

        if (statusMessage) {
            let alertClass = 'alert-info';
            let icon = 'fas fa-info-circle';

            switch(type) {
                case 'connected':
                    alertClass = 'alert-success';
                    icon = 'fas fa-check-circle';
                    break;
                case 'disconnected':
                    alertClass = 'alert-danger';
                    icon = 'fas fa-exclamation-triangle';
                    break;
                case 'connecting':
                    alertClass = 'alert-warning';
                    icon = 'fas fa-spinner fa-spin';
                    break;
            }

            statusMessage.className = 'alert ' + alertClass + ' mt-2';
            statusMessage.innerHTML = '<i class="' + icon + '"></i> ' + message;
        }
    }

    function addLogToPage(type, message) {
        const timestamp = new Date().toLocaleTimeString();
        let color = '#333';
        let icon = 'ℹ️';

        switch(type) {
            case 'success': color = '#28a745'; icon = '✅'; break;
            case 'error': color = '#dc3545'; icon = '❌'; break;
            case 'warning': color = '#ffc107'; icon = '⚠️'; break;
            case 'info': color = '#17a2b8'; icon = '📋'; break;
        }

        const logDiv = $('#deploymentLogsLive');
        logDiv.append('<div style="color: ' + color + '; margin: 2px 0;">[' + timestamp + '] ' + icon + ' ' + message + '</div>');
        logDiv.scrollTop(logDiv[0].scrollHeight);
    }

    function showSuccessButtons() {
        const crmLink = document.getElementById('crmLink');

        if (crmLink) {
            let httpsUrl = companyUrl;
            if (httpsUrl.startsWith('http://')) {
                httpsUrl = httpsUrl.replace('http://', 'https://');
            } else if (!httpsUrl.startsWith('https://')) {
                httpsUrl = 'https://' + httpsUrl;
            }
            crmLink.href = httpsUrl + '/crm-panel';
        }

        document.getElementById('actionButtons').style.display = 'block';
        document.getElementById('successButtons').style.display = 'block';
    }

    function showErrorButtons() {
        document.getElementById('actionButtons').style.display = 'block';
        document.getElementById('errorButtons').style.display = 'block';
    }

    function showTimeoutButtons() {
        document.getElementById('actionButtons').style.display = 'block';
        document.getElementById('timeoutButtons').style.display = 'block';
    }

    function handleTimeout() {
        clearMonitoring();
        updateStatus('Monitoring timeout reached. Deployment may still be in progress.', 'disconnected');
        addLogToPage('warning', 'Monitoring timeout reached after 10 minutes.');
        showTimeoutButtons();
    }

    function clearMonitoring() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        if (deploymentTimeout) {
            clearTimeout(deploymentTimeout);
            deploymentTimeout = null;
        }

        isMonitoring = false;
    }

    function checkDeploymentStatus() {
        window.location.reload();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        clearMonitoring();
    });
</script>