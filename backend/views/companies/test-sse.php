<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */

$this->title = 'SSE Connection Test';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss('
.test-container {
    max-width: 800px;
    margin: 20px auto;
}

.test-logs {
    background: #1a1a1a;
    color: #00ff00;
    font-family: "Courier New", monospace;
    padding: 15px;
    border-radius: 5px;
    max-height: 400px;
    overflow-y: auto;
    margin-top: 15px;
}

.test-entry {
    margin-bottom: 5px;
    font-size: 13px;
}

.test-entry.error {
    color: #ff4444;
}

.test-entry.success {
    color: #44ff44;
}

.test-entry.info {
    color: #4444ff;
}

.connection-status {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-weight: bold;
}

.status-connecting {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-connected {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.progress {
    height: 25px;
    margin: 15px 0;
}
');
?>

<div class="test-container">
    <div class="card">
        <div class="card-header">
            <h4><i class="fas fa-satellite-dish"></i> Server-Sent Events (SSE) Connection Test</h4>
            <p class="mb-0 small text-muted">This test will check if your server supports real-time SSE connections for deployment monitoring</p>
        </div>

        <div class="card-body">
            <div id="connection-status" class="connection-status status-connecting">
                <i class="fas fa-spinner fa-spin"></i> Connecting to SSE stream...
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <button id="start-sse-test" class="btn btn-primary btn-block">
                        <i class="fas fa-play"></i> Start SSE Test
                    </button>
                </div>
                <div class="col-md-6">
                    <button id="start-ajax-test" class="btn btn-secondary btn-block">
                        <i class="fas fa-sync"></i> Test AJAX Alternative
                    </button>
                </div>
            </div>

            <div class="progress" style="display: none;" id="test-progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar"
                     style="width: 0%"
                     id="progress-bar">0%</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-terminal"></i> Test Log Output</h6>
                    <div class="card-tools">
                        <button id="clear-logs" class="btn btn-sm btn-outline-secondary">Clear</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="test-logs" id="test-logs">
                        <div class="test-entry">Waiting for test to start...</div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-success">
                            <div class="card-header">SSE Messages</div>
                            <div class="card-body text-center">
                                <h3 id="sse-counter">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-info">
                            <div class="card-header">AJAX Requests</div>
                            <div class="card-body text-center">
                                <h3 id="ajax-counter">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-warning">
                            <div class="card-header">Errors</div>
                            <div class="card-body text-center">
                                <h3 id="error-counter">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let sseCounter = 0;
        let ajaxCounter = 0;
        let errorCounter = 0;
        let currentEventSource = null;
        let ajaxInterval = null;

        const statusDiv = document.getElementById('connection-status');
        const logsDiv = document.getElementById('test-logs');
        const progressDiv = document.getElementById('test-progress');
        const progressBar = document.getElementById('progress-bar');

        // Start SSE test
        document.getElementById('start-sse-test').addEventListener('click', function() {
            clearTest();
            addLog('info', 'Starting SSE connection test...');
            setStatus('connecting', 'Connecting to SSE stream...');
            progressDiv.style.display = 'block';

            startSSETest();
        });

        // Start AJAX test
        document.getElementById('start-ajax-test').addEventListener('click', function() {
            clearTest();
            addLog('info', 'Starting AJAX polling test...');
            setStatus('connecting', 'Starting AJAX polling...');
            progressDiv.style.display = 'block';

            startAjaxTest();
        });

        // Clear logs
        document.getElementById('clear-logs').addEventListener('click', function() {
            clearTest();
        });

        function startSSETest() {
            if (currentEventSource) {
                currentEventSource.close();
            }

            const eventSource = new EventSource('<?= Url::to(['test-sse']) ?>');
            currentEventSource = eventSource;

            eventSource.onopen = function(event) {
                addLog('success', 'SSE connection opened successfully!');
                setStatus('connected', 'SSE connection active');
            };

            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    sseCounter++;
                    updateCounter('sse-counter', sseCounter);

                    addLog('success', `SSE Message #${data.test_number}: ${data.message}`);

                    if (data.progress) {
                        updateProgress(data.progress);
                    }

                    if (data.status === 'completed') {
                        addLog('success', '✅ SSE test completed successfully!');
                        setStatus('connected', 'SSE test completed - YOUR SERVER SUPPORTS SSE!');
                        eventSource.close();
                    }

                } catch (e) {
                    errorCounter++;
                    updateCounter('error-counter', errorCounter);
                    addLog('error', 'Error parsing SSE data: ' + e.message);
                }
            };

            eventSource.onerror = function(event) {
                errorCounter++;
                updateCounter('error-counter', errorCounter);
                addLog('error', 'SSE connection error - SSE may not be supported on this server');
                setStatus('error', 'SSE connection failed - Try AJAX alternative');
                eventSource.close();
            };
        }

        function startAjaxTest() {
            let ajaxTestCounter = 0;
            const maxRequests = 10;

            ajaxInterval = setInterval(function() {
                ajaxTestCounter++;
                ajaxCounter++;
                updateCounter('ajax-counter', ajaxCounter);

                // Simulate deployment progress request
                $.ajax({
                    url: '<?= Url::to(['get-live-progress']) ?>',
                    type: 'GET',
                    data: {
                        companyId: 1, // Test company ID
                        lastDetailId: ajaxTestCounter
                    },
                    dataType: 'json',
                    success: function(response) {
                        addLog('success', `AJAX Request #${ajaxTestCounter}: Response received`);
                        updateProgress(ajaxTestCounter * 10);

                        if (ajaxTestCounter >= maxRequests) {
                            clearInterval(ajaxInterval);
                            addLog('success', '✅ AJAX polling test completed successfully!');
                            setStatus('connected', 'AJAX polling works - Alternative method available');
                        }
                    },
                    error: function(xhr, status, error) {
                        errorCounter++;
                        updateCounter('error-counter', errorCounter);
                        addLog('error', `AJAX Request #${ajaxTestCounter} failed: ${error}`);
                    }
                });

                if (ajaxTestCounter >= maxRequests) {
                    clearInterval(ajaxInterval);
                }

            }, 1000);
        }

        function addLog(type, message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `test-entry ${type}`;

            let icon = '';
            switch(type) {
                case 'success': icon = '✅'; break;
                case 'error': icon = '❌'; break;
                case 'info': icon = 'ℹ️'; break;
                default: icon = '•'; break;
            }

            logEntry.innerHTML = `[${timestamp}] ${icon} ${escapeHtml(message)}`;
            logsDiv.appendChild(logEntry);
            logsDiv.scrollTop = logsDiv.scrollHeight;
        }

        function setStatus(type, message) {
            statusDiv.className = `connection-status status-${type}`;

            let icon = '';
            switch(type) {
                case 'connecting': icon = '<i class="fas fa-spinner fa-spin"></i>'; break;
                case 'connected': icon = '<i class="fas fa-check-circle"></i>'; break;
                case 'error': icon = '<i class="fas fa-exclamation-triangle"></i>'; break;
            }

            statusDiv.innerHTML = `${icon} ${message}`;
        }

        function updateProgress(progress) {
            progressBar.style.width = progress + '%';
            progressBar.textContent = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);

            if (progress >= 100) {
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
            }
        }

        function updateCounter(id, value) {
            document.getElementById(id).textContent = value;
        }

        function clearTest() {
            if (currentEventSource) {
                currentEventSource.close();
                currentEventSource = null;
            }

            if (ajaxInterval) {
                clearInterval(ajaxInterval);
                ajaxInterval = null;
            }

            logsDiv.innerHTML = '<div class="test-entry">Logs cleared. Ready for new test...</div>';
            progressDiv.style.display = 'none';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('progress-bar-animated');

            sseCounter = 0;
            ajaxCounter = 0;
            errorCounter = 0;
            updateCounter('sse-counter', 0);
            updateCounter('ajax-counter', 0);
            updateCounter('error-counter', 0);

            setStatus('connecting', 'Ready to start test...');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
</script>