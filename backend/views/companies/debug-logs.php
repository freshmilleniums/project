<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = 'Deployment Logs Debug';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss('
.logs-debug-container {
    margin: 20px auto;
}

.log-entry-card {
    margin-bottom: 15px;
    border-left: 4px solid #007bff;
}

.log-entry-card.error {
    border-left-color: #dc3545;
}

.log-detail {
    font-size: 12px;
    background: #f8f9fa;
    padding: 8px;
    margin-top: 5px;
}

.refresh-logs {
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 1000;
}
');

// Register JavaScript with proper syntax
$script = "
var debugLogsUrls = {
    companyLogs: '" . Url::to(['debug-company-logs']) . "',
    companyLogDetails: '" . Url::to(['debug-company-log-details']) . "',
    deploymentStream: '" . Url::to(['deployment-stream']) . "'
};

function loadLogs() {
    // Load company logs
    $.ajax({
        url: debugLogsUrls.companyLogs,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderCompanyLogs(response.logs);
            } else {
                $('#company-logs').html('<div class=\"alert alert-danger\">Error loading logs: ' + response.message + '</div>');
            }
        },
        error: function() {
            $('#company-logs').html('<div class=\"alert alert-danger\">Error loading company logs</div>');
        }
    });

    // Load company log details  
    $.ajax({
        url: debugLogsUrls.companyLogDetails,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderCompanyLogDetails(response.details);
            } else {
                $('#company-log-details').html('<div class=\"alert alert-danger\">Error loading details: ' + response.message + '</div>');
            }
        },
        error: function() {
            $('#company-log-details').html('<div class=\"alert alert-danger\">Error loading log details</div>');
        }
    });
}

function renderCompanyLogs(logs) {
    var html = '';
    
    if (logs.length === 0) {
        html = '<div class=\"alert alert-info\">No company logs found</div>';
    } else {
        logs.forEach(function(log) {
            var dateStr = new Date(log.date * 1000).toLocaleString();
            html += '<div class=\"card log-entry-card\">';
            html += '<div class=\"card-body\">';
            html += '<h6 class=\"card-title\">Log #' + log.id + ' - ' + log.action_type + '</h6>';
            html += '<p class=\"card-text\">';
            html += '<strong>Company:</strong> ' + (log.company_name || 'Unknown') + ' (ID: ' + log.company_id + ')<br>';
            html += '<strong>User:</strong> ' + (log.user_name || 'System') + '<br>';
            html += '<strong>Date:</strong> ' + dateStr + '<br>';
            html += '<strong>Details Count:</strong> ' + log.details_count;
            html += '</p>';
            html += '</div>';
            html += '</div>';
        });
    }
    
    $('#company-logs').html(html);
}

function renderCompanyLogDetails(details) {
    var html = '';
    
    if (details.length === 0) {
        html = '<div class=\"alert alert-info\">No log details found</div>';
    } else {
        details.forEach(function(detail) {
            var isError = detail.data && detail.data.includes('\"error\":true');
            html += '<div class=\"card log-entry-card ' + (isError ? 'error' : '') + '\">';
            html += '<div class=\"card-body\">';
            html += '<h6 class=\"card-title\">Detail #' + detail.id + ' (Log #' + detail.logs_company_id + ')</h6>';
            html += '<p class=\"card-text\">';
            html += '<strong>Type:</strong> ' + detail.data_type + '<br>';
            
            if (detail.data_type === 'json') {
                try {
                    var jsonData = JSON.parse(detail.data);
                    html += '<strong>Step:</strong> ' + (jsonData.step || 'N/A') + '<br>';
                    html += '<strong>Progress:</strong> ' + (jsonData.progress || 'N/A') + '%<br>';
                    html += '<strong>Timestamp:</strong> ' + (jsonData.timestamp || 'N/A') + '<br>';
                    if (jsonData.error) {
                        html += '<strong>Error:</strong> ' + (jsonData.message || 'Unknown error') + '<br>';
                    }
                } catch (e) {
                    html += '<strong>Invalid JSON</strong><br>';
                }
            }
            
            html += '</p>';
            html += '<div class=\"log-detail\">';
            html += '<strong>Raw Data:</strong><br>';
            html += '<pre style=\"font-size: 10px; max-height: 100px; overflow-y: auto;\">' + escapeHtml(detail.data) + '</pre>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
    }
    
    $('#company-log-details').html(html);
}

var autoRefreshInterval = null;
var sseSource = null;
var sseMessageCount = 0;

$(document).ready(function() {
    // Load logs on page load
    loadLogs();

    // Refresh logs
    $('#refresh-logs').on('click', function() {
        loadLogs();
    });

    // Auto refresh toggle
    $('#auto-refresh').on('click', function() {
        if ($(this).hasClass('active')) {
            // Stop auto refresh
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
            $(this).html('<i class=\"fas fa-play\"></i> Auto');
        } else {
            // Start auto refresh
            autoRefreshInterval = setInterval(loadLogs, 3000);
            $(this).html('<i class=\"fas fa-pause\"></i> Stop');
        }
    });

    // SSE Monitor
    $('#start-sse-monitor').on('click', function() {
        var companyId = $('#monitor-company-id').val();
        if (!companyId) {
            alert('Please enter a Company ID');
            return;
        }
        
        startSSEMonitor(companyId);
    });

    $('#stop-sse-monitor').on('click', function() {
        stopSSEMonitor();
    });

    $('#clear-sse-logs').on('click', function() {
        $('#sse-logs').html('');
        addSSELog('📝 SSE logs cleared');
    });
});

function startSSEMonitor(companyId) {
    $('#start-sse-monitor').prop('disabled', true);
    $('#stop-sse-monitor').prop('disabled', false);
    $('#sse-logs').show().html('');
    sseMessageCount = 0;
    
    updateSSEStatus('connecting', 'Connecting to SSE stream for company ' + companyId + '...');
    
    // Check if test mode is selected
    var isTestMode = $('#test-mode').is(':checked');
    var sseUrl = debugLogsUrls.deploymentStream + '?id=' + companyId;
    
    if (isTestMode) {
        sseUrl += '&test=1';
        addSSELog('🧪 TEST MODE: Connecting to: ' + sseUrl);
    } else {
        addSSELog('🚀 REAL MODE: Connecting to: ' + sseUrl);
        addSSELog('📋 Monitoring real deployment progress for company ' + companyId);
    }
    
    sseSource = new EventSource(sseUrl);
    
    // Enhanced error detection
    var connectionTimeout = setTimeout(function() {
        if (sseMessageCount === 0) {
            addSSELog('⏰ Connection timeout - no messages received in 10 seconds');
            addSSELog('💡 Possible issues:');
            addSSELog('   - Server blocking SSE connections');
            addSSELog('   - CORS or security policy issues');
            addSSELog('   - Incorrect URL: ' + sseUrl);
            addSSELog('   - PHP execution errors');
            
            // Try a test request to see if the endpoint exists
            $.ajax({
                url: debugLogsUrls.deploymentStream + '?id=' + companyId + '&test=1',
                method: 'GET',
                timeout: 5000,
                success: function(response) {
                    addSSELog('✅ HTTP request to endpoint succeeded');
                    addSSELog('📄 Response: ' + (typeof response === 'string' ? response.substring(0, 100) : JSON.stringify(response).substring(0, 100)));
                },
                error: function(xhr, status, error) {
                    addSSELog('❌ HTTP request failed: ' + error + ' (Status: ' + xhr.status + ')');
                    var responseText = xhr.responseText || '';
                    addSSELog('📄 Response text: ' + responseText.substring(0, 100));
                }
            });
        }
    }, 10000);
    
    sseSource.onopen = function(event) {
        clearTimeout(connectionTimeout);
        updateSSEStatus('connected', 'SSE connection established');
        addSSELog('✅ SSE connection opened successfully');
        if (sseSource && sseSource.readyState !== undefined) {
            addSSELog('🎯 ReadyState: ' + sseSource.readyState);
        }
    };
    
    sseSource.onmessage = function(event) {
        clearTimeout(connectionTimeout);
        sseMessageCount++;
        try {
            var data = JSON.parse(event.data);
            
            // Format JSON nicely for display
            var jsonFormatted = JSON.stringify(data, null, 2);
            addSSELog('#' + sseMessageCount + ': JSON data received');
            addJSONLog(jsonFormatted);
            
            if (data.status === 'connected' || data.status === 'test_mode') {
                updateSSEStatus('connected', 'Connected to deployment stream');
            } else if (data.heartbeat) {
                updateSSEStatus('connected', 'Heartbeat received #' + data.iteration);
            } else if (data.step) {
                updateSSEStatus('connected', 'Received: ' + data.step);
            } else if (data.test_number) {
                updateSSEStatus('connected', 'Test message #' + data.test_number + ' received');
            }
        } catch (e) {
            addSSELog('❌ Error parsing message: ' + e.message);
            addSSELog('📄 Raw data: ' + event.data);
        }
    };
    
    sseSource.onerror = function(event) {
        clearTimeout(connectionTimeout);
        addSSELog('❌ SSE connection closed (this is normal for test mode)');
        addSSELog('🔍 Connection details:');
        if (sseSource && sseSource.readyState !== undefined) {
            addSSELog('   - ReadyState: ' + sseSource.readyState);
        }
        addSSELog('   - Total messages received: ' + sseMessageCount);
        
        // Log browser console errors
        console.log('SSE connection ended normally after test completion');
        
        updateSSEStatus('info', 'SSE test completed - connection closed normally');
        
        // FIXED: Do not show as error, this is normal behavior for test
        // Auto-restart after 3 seconds only in test mode  
        var isTestMode = $('#test-mode').is(':checked');
        if (isTestMode) {
            setTimeout(function() {
                if ($('#start-sse-monitor').prop('disabled')) {
                    addSSELog('🔄 Auto-reconnecting for continuous testing...');
                    startSSEMonitor($('#monitor-company-id').val());
                }
            }, 3000);
        } else {
            addSSELog('📋 Real deployment monitoring ended');
            updateSSEStatus('disconnected', 'Real deployment stream ended');
        }
    };
}

function stopSSEMonitor() {
    if (sseSource) {
        sseSource.close();
        sseSource = null;
    }
    
    $('#start-sse-monitor').prop('disabled', false);
    $('#stop-sse-monitor').prop('disabled', true);
    updateSSEStatus('disconnected', 'SSE monitor stopped');
    addSSELog('🔌 SSE monitor stopped');
}

function updateSSEStatus(type, message) {
    var statusDiv = $('#sse-status');
    statusDiv.removeClass('alert-secondary alert-success alert-danger alert-warning');
    
    switch(type) {
        case 'connecting':
            statusDiv.addClass('alert-warning');
            break;
        case 'connected':
            statusDiv.addClass('alert-success');
            break;
        case 'error':
            statusDiv.addClass('alert-danger');
            break;
        default:
            statusDiv.addClass('alert-secondary');
    }
    
    statusDiv.html('<i class=\"fas fa-satellite-dish\"></i> ' + message);
}

function addSSELog(message) {
    var timestamp = new Date().toLocaleTimeString();
    var logDiv = $('#sse-logs');
    var formattedMessage = '[' + timestamp + '] ' + message;
    logDiv.append(formattedMessage + '\\n');
    logDiv.scrollTop(logDiv[0].scrollHeight);
}

function addJSONLog(jsonString) {
    var logDiv = $('#sse-logs');
    logDiv.append(jsonString + '\\n\\n');
    logDiv.scrollTop(logDiv[0].scrollHeight);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
";

$this->registerJs($script, \yii\web\View::POS_END);
?>

<div class="logs-debug-container">
    <div class="refresh-logs">
        <button id="refresh-logs" class="btn btn-primary">
            <i class="fas fa-sync"></i> Refresh
        </button>
        <button id="auto-refresh" class="btn btn-secondary" data-toggle="button">
            <i class="fas fa-play"></i> Auto
        </button>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-database"></i> Company Logs (Main)</h5>
                    <small class="text-muted">logs_company table</small>
                </div>
                <div class="card-body" id="company-logs" style="max-height: 600px; overflow-y: auto;">
                    <div class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Loading logs...
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Company Log Details</h5>
                    <small class="text-muted">logs_company_details table</small>
                </div>
                <div class="card-body" id="company-log-details" style="max-height: 600px; overflow-y: auto;">
                    <div class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Loading details...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-terminal"></i> SSE Stream Test</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label>Company ID to monitor:</label>
                            <input type="number" id="monitor-company-id" class="form-control" value="9" min="1">
                        </div>
                        <div class="col-md-6">
                            <label>Monitor mode:</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="monitor-mode" id="test-mode" value="test" checked>
                                <label class="form-check-label" for="test-mode">Test Mode</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="monitor-mode" id="real-mode" value="real">
                                <label class="form-check-label" for="real-mode">Real Deployment</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>&nbsp;</label><br>
                            <button id="start-sse-monitor" class="btn btn-success">
                                <i class="fas fa-satellite-dish"></i> Start SSE Monitor
                            </button>
                            <button id="stop-sse-monitor" class="btn btn-danger" disabled>
                                <i class="fas fa-stop"></i> Stop
                            </button>
                            <button id="clear-sse-logs" class="btn btn-secondary">
                                <i class="fas fa-trash"></i> Clear Logs
                            </button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div id="sse-status" class="alert alert-secondary">
                            <i class="fas fa-info-circle"></i> SSE monitor not started
                        </div>
                        <div id="sse-logs" style="background: #1a1a1a; color: #00ff00; font-family: monospace; padding: 10px; max-height: 200px; overflow-y: auto; display: none;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>