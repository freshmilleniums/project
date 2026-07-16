<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use common\models\Companies;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\CompaniesSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Companies';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss('
.company-tabs .nav-tabs {
    margin-bottom: 20px;
}

.company-info-section {
    margin-bottom: 20px;
}

.company-actions {
    margin-top: 20px;
}

.company-actions .btn {
    margin-right: 10px;
}

/* Debug deployment modal styles */
.deployment-debug-container .card {
    margin-bottom: 15px;
}

.deployment-debug-container .log-entry {
    padding: 8px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.deployment-debug-container .log-entry:last-child {
    border-bottom: none;
}

.deployment-debug-container .deployment-logs {
    max-height: 400px;
    overflow-y: auto;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
}

.deployment-debug-container .progress {
    height: 25px;
}

.deployment-debug-container .badge {
    font-size: 11px;
    margin-right: 8px;
}

.deployment-debug-container pre {
    font-size: 12px;
    margin-top: 8px;
    background: #f1f3f4;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 8px;
    white-space: pre-wrap;
}

#deploymentDebugModal .modal-dialog {
    max-width: 95%;
}

@media (min-width: 1200px) {
    #deploymentDebugModal .modal-dialog {
        max-width: 1140px;
    }
}
');

// Enhanced JS with debug deployment functionality for ALL deployment types
$script = "

function getActionUrl(action) {
    var url = '';
    if (action === 'view') {
        url = '" . Url::to(['view']) . "';       
    } else if (action === 'update') {
        url = '" . Url::to(['update']) . "';       
    }
    return url;
}

function showActionDetails(event, action, companyId) {
    event.preventDefault();
    event.stopPropagation();
    
    var \$clickedRow = $(event.target).closest('tr');
    var existingActionRow = \$clickedRow.next('.action-details-row');    
   
    // If action row already exists
    if (existingActionRow.length) {
        var currentAction = existingActionRow.data('current-action');        
      
        // If same action clicked, close it
        if (currentAction === action) {
            existingActionRow.find('.action-details').slideUp(500, function() {
                existingActionRow.remove();
            });
            return;
        }        
       
        // Different action clicked, update content
        var url = getActionUrl(action);
        var contentDiv = existingActionRow.find('.content');
        
        existingActionRow.data('current-action', action);        
        
        contentDiv.fadeOut(200, function() {
            $(this).html('Loading...');
            $(this).fadeIn(200);            
         
            $.ajax({
                url: url,
                type: 'GET',
                data: { id: companyId },             
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (typeof response.tpl != 'undefined') {
                            contentDiv.fadeOut(200, function() {
                                $(this).html(response.tpl);
                                $(this).fadeIn(300);
                                // Force width constraints after content is inserted
                                forceWidthConstraints();
                            });
                        }
                    } catch(e) {
                        console.error('JSON Parse Error:', e);
                        contentDiv.html('Error loading data');
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
   
    // Close all other open action rows
    $('.action-details-row').each(function() {
        var \$this = $(this);
        \$this.find('.action-details').slideUp(700, function() {
            \$this.remove();
        });
    });    
   
    // Create new action row
    var actionRow = $('<tr class=\"action-details-row\"><td colspan=\"' + \$clickedRow.find('td').length + '\"><div class=\"action-details\"><div class=\"content\">Loading...</div></div></td></tr>');
    actionRow.data('current-action', action);
    
    \$clickedRow.after(actionRow);
    
    var url = getActionUrl(action);
    
    // Load content via AJAX
    $.ajax({
        url: url,
        type: 'GET',
        data: { id: companyId },    
        success: function(response) {
            try {
                response = JSON.parse(response);
                if (typeof response.tpl != 'undefined') {
                    actionRow.find('.content').html(response.tpl);
                    actionRow.find('.action-details').hide().slideDown(700, function() {
                        // Force width constraints after animation completes
                        forceWidthConstraints();
                    });
                } else {
                    console.error('No tpl in response');
                    actionRow.find('.content').html('Error: Invalid response format');
                }
            } catch(e) {
                console.error('JSON Parse Error:', e);
                actionRow.find('.content').html('Error parsing response');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            actionRow.find('.content').html('Failed to load data');
            actionRow.find('.action-details').hide().slideDown(700);
        }
    });
}

// Function to force width constraints on action-details content
function forceWidthConstraints() {
    if ($(window).width() <= 768) {
        $('.action-details').each(function() {
            var \$actionDetails = $(this);
            
            // Remove any inline width styles
            \$actionDetails.css({
                'width': '100%',
                'max-width': '100%',
                'min-width': '0'
            });
            
            // Force all form controls to 100% width
            \$actionDetails.find('input, select, textarea, .form-control').each(function() {
                $(this).css({
                    'width': '100%',
                    'max-width': '100%',
                    'min-width': '0'
                });
            });
            
            // Fix input groups
            \$actionDetails.find('.input-group').each(function() {
                $(this).css({
                    'width': '100%',
                    'max-width': '100%',
                    'display': 'flex',
                    'flex-direction': 'column'
                });
            });
            
            \$actionDetails.find('.input-group-append').each(function() {
                $(this).css({
                    'width': '100%',
                    'display': 'flex',
                    'margin-left': '0'
                });
            });
        });
    }
}

// Handle company update form submission
$(document).on('click', '.update-company-send', function (e){
    e.preventDefault();
    
    let button = $(this);
    let form = button.closest('form');
    let action = form.prop('action');
    let data = form.serialize();    
   
    // Check for validation errors
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
                   
                    // Close action details
                    $('.action-details-row').find('.action-details').slideUp(700, function() {
                        $('.action-details-row').remove();
                    });
                    
                    // Reload page to refresh data
                    location.reload();
                } else {                    
                    if (typeof response.tpl != 'undefined') {
                        button.closest('.content').html(response.tpl);
                        // Force width constraints after error display
                        forceWidthConstraints();
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

// Enhanced company action handler 
$(document).on('click', '.company-action-btn, .company-action-link', function (e){
    e.preventDefault();
    
    let button = $(this);
    let companyId = button.data('company-id');
    let action = button.data('action');
    let confirmMessage = button.data('confirm-message');
    
    // Show confirmation dialog if needed
    if (confirmMessage && !confirm(confirmMessage)) {
        return false;
    }
    
    // Special handling for ALL deploy actions - show debug modal
    if (action === 'deploy' || action === 'deploy-crm' ) {
        handleDebugDeploy(button, companyId, action);
        return;
    }
    
    // Regular handling for other actions (stop, start, delete)
    button.prop('disabled', true);
    let originalText = button.html();
    
    let url = '';
    let loadingText = '';
    
    if (action === 'stop') {
        url = '" . Url::to(['stop']) . "' + '?id=' + companyId;
        loadingText = '<i class=\"fas fa-spinner fa-spin\"></i> Stopping...';
    } else if (action === 'start') {
        url = '" . Url::to(['start']) . "' + '?id=' + companyId;
        loadingText = '<i class=\"fas fa-spinner fa-spin\"></i> Starting...';
    } else if (action === 'delete') {
        url = '" . Url::to(['delete']) . "' + '?id=' + companyId;
        loadingText = '<i class=\"fas fa-spinner fa-spin\"></i> Deleting...';
    } else if (action === 'update-config') {
        url = '" . Url::to(['update-config']) . "' + '?id=' + companyId;       
        loadingText = '<i class=\"fas fa-sync-alt fa-spin\"></i> Updating...';      
    }
    
    button.html(loadingText);
    
    $.ajax({
        type: 'POST',
        url: url,
        data: { id: companyId },
        dataType: 'json',
        success: function (response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            if (typeof response.success != 'undefined') {
                if(response.success == true) {
                    toastr.success(response.message);
                    
                    // Close action details and reload page
                    $('.action-details-row').find('.action-details').slideUp(700, function() {
                        $('.action-details-row').remove();
                    });
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(response.message);
                }
            }
        },
        error: function() {
            toastr.error('An error occurred while processing request');
        },
        complete: function() {
            button.prop('disabled', false).html(originalText);
        }
    });
});

// DEBUG Deploy Handler
function handleDebugDeploy(button, companyId, action) {
    // Disable button and show loading
    button.prop('disabled', true);
    let originalText = button.html();    
   
    let deployUrl = '';
    let loadingText = '';
    
    switch(action) {
        case 'deploy-crm':
            deployUrl = '" . Url::to(['deploy-crm']) . "';
            loadingText = '<i class=\"fas fa-server fa-spin\"></i> Starting CRM...';
            break;
        case 'update-config':
            deployUrl = '" . Url::to(['update-config']) . "';
            loadingText = '<i class=\"fas fa-sync-alt fa-spin\"></i> Updating...';
            break;   
        case 'test-auto-deploy':
            deployUrl = '" . Url::to(['test-auto-deploy']) . "';
            loadingText = '<i class=\"fas fa-flask fa-spin\"></i> Starting Test...';
            break;
        default: // deploy
            deployUrl = '" . Url::to(['deploy']) . "';
            loadingText = '<i class=\"fas fa-rocket fa-spin\"></i> Starting Deploy...';
    }
    
    button.html(loadingText);
    
    $.ajax({
        url: deployUrl + '?id=' + companyId,
        method: 'POST',
        data: { id: companyId },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            console.log('Deploy response (' + action + '):', response);
            
            if (response.success) {
                if (response.show_progress) {
                    // DEBUG MODE: Show progress in modal
                    showDebugDeploymentModal(response, companyId, action);
                } else {
                    // Normal mode: redirect or reload
                    if (response.redirectUrl) {
                        window.location.href = response.redirectUrl;
                    } else {
                        toastr.success(response.message);
                        location.reload();
                    }
                }
            } else {
                toastr.error(response.message);
                if (response.debug_trace) {
                    console.error('Debug trace:', response.debug_trace);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Deploy AJAX Error (' + action + '):', xhr.responseText);
            
            if (xhr.responseText && xhr.responseText.indexOf('<!DOCTYPE') !== -1) {
                toastr.error('Server returned HTML instead of JSON. Check if action method exists in controller.');
                console.error('Server returned HTML page instead of JSON. Possible missing action method: ' + action);
            } else {
                toastr.error('Deployment request failed: ' + error + ' (Status: ' + status + ')');
            }
        },
        complete: function() {
            // Re-enable button
            button.prop('disabled', false).html(originalText);
        }
    });
}

function showDebugDeploymentModal(response, companyId, action) {
    // Remove existing modal
    $('#deploymentDebugModal').remove();
    
    let modalTitle = '';
    let modalColor = '';
    
    switch(action) {
        case 'deploy-crm':
            modalTitle = '<i class=\"fas fa-server\"></i> CRM Deployment Progress';
            modalColor = 'bg-success';
            break;   
        case 'test-auto-deploy':
            modalTitle = '<i class=\"fas fa-flask\"></i> Test Deployment Progress';
            modalColor = 'bg-info';
            break;
        default:
            modalTitle = '<i class=\"fas fa-rocket\"></i> Deployment Progress';
            modalColor = 'bg-primary';
    }
    
    // Create modal with working SSE
    const modal = $('<div class=\"modal fade\" id=\"deploymentDebugModal\" tabindex=\"-1\" role=\"dialog\" data-backdrop=\"static\" data-keyboard=\"false\">' +
        '<div class=\"modal-dialog modal-xl\" role=\"document\">' +
            '<div class=\"modal-content\">' +
                '<div class=\"modal-header ' + modalColor + ' text-white\">' +
                    '<h5 class=\"modal-title\">' + modalTitle + '</h5>' +
                    '<button type=\"button\" class=\"close text-white\" onclick=\"closeModal()\" aria-label=\"Close\">' +
                        '<span aria-hidden=\"true\">&times;</span>' +
                    '</button>' +
                '</div>' +
                '<div class=\"modal-body p-3\">' +
                    '<div class=\"row\">' +
                        '<div class=\"col-md-12\">' +
                            '<div id=\"deploymentProgressContent\">' + (response.progress_html || '<p>Initializing...</p>') + '</div>' +
                            '<div class=\"progress mt-3\" style=\"height: 25px;\">' +
                                '<div id=\"liveProgressBar\" class=\"progress-bar progress-bar-striped progress-bar-animated ' + (action === 'deploy-crm' ? 'bg-success' : 'bg-info') + '\" style=\"width: 5%\">5%</div>' +
                            '</div>' +
                            '<div id=\"deploymentStatusLive\" class=\"alert alert-info mt-2\">' +
                                '<i class=\"fas fa-satellite-dish\"></i> Connecting to deployment stream...' +
                            '</div>' +
                            '<div id=\"deploymentLogsLive\" class=\"mt-3\" style=\"max-height: 250px; overflow-y: auto; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px;\">' +
                                '<div style=\"color: #666;\">[' + new Date().toLocaleTimeString() + '] Starting deployment monitoring...</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class=\"modal-footer\">' +
                    '<button type=\"button\" class=\"btn btn-secondary\" onclick=\"closeModal()\">Close</button>' +
                '</div>' +
            '</div>' +
        '</div>' +
    '</div>');
    
    $('body').append(modal);
    $('#deploymentDebugModal').modal('show');    
    
    startLiveDeploymentMonitoring(companyId, action);
    
    // Show debug info in console
    if (response.debug_info) {
        console.log('Deployment Debug Info (' + action + '):', response.debug_info);
    }
}

function closeModal() {  
    if (window.currentEventSource && window.currentEventSource.readyState !== EventSource.CLOSED) {
        window.currentEventSource.close();
        console.log(' SSE connection closed by user');
    }
    $('#deploymentDebugModal').modal('hide');
}

function startLiveDeploymentMonitoring(companyId, deploymentType) {
    console.log(' Starting SSE monitoring for company:', companyId);    
   
    let deploymentFinished = false;
    let reconnectAttempts = 0;
    let maxReconnectAttempts = 3;
    
    // Update status
    $('#deploymentStatusLive').html('<i class=\"fas fa-wifi\"></i> Connecting to real-time stream...');
    
    if (typeof(EventSource) !== \"undefined\") {        
        const eventSource = new EventSource('/crm-panel/companies/deployment-stream?id=' + companyId);        
      
        window.currentEventSource = eventSource;        
       
        const forceCloseTimeout = setTimeout(function() {
            if (!deploymentFinished) {
                deploymentFinished = true;
                eventSource.close();
                console.log('SSE closed by timeout (3 minutes)');
                $('#deploymentStatusLive').removeClass().addClass('alert alert-warning');
                $('#deploymentStatusLive').html('<i class=\"fas fa-clock\"></i> Monitoring timeout - deployment continues in background');
                addLogToModal('warning', 'Monitoring timeout after 3 minutes');
            }
        }, 180000);
        
        eventSource.onopen = function(event) {
            console.log('✅ SSE Connected!');
            reconnectAttempts = 0; 
            $('#deploymentStatusLive').removeClass('alert-info alert-warning').addClass('alert-success');
            $('#deploymentStatusLive').html('<i class=\"fas fa-check-circle\"></i> Connected to real-time monitoring');
            addLogToModal('success', 'Connected to deployment stream');
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
                    clearTimeout(forceCloseTimeout);
                    console.log('SSE stopped - deployment finished with status:', data.status);                    
                  
                    if (data.status === 'completed') {
                        $('#deploymentStatusLive').removeClass().addClass('alert alert-success');
                        $('#deploymentStatusLive').html('<i class=\"fas fa-check-circle\"></i> ✅ Deployment completed successfully!');
                        addLogToModal('success', 'Deployment completed successfully!');                        
                      
                        $('.modal-footer').html(
                            '<button type=\"button\" class=\"btn btn-secondary ml-2\" onclick=\"location.reload()\">Close & Reload</button>'
                        );
                    } else if (data.status === 'failed') {
                        $('#deploymentStatusLive').removeClass().addClass('alert alert-danger');
                        $('#deploymentStatusLive').html('<i class=\"fas fa-exclamation-triangle\"></i> ❌ Deployment failed');
                        addLogToModal('error', 'Deployment failed: ' + (data.message || 'Unknown error'));
                    } else if (data.status === 'not_deploying') {
                        $('#deploymentStatusLive').removeClass().addClass('alert alert-warning');
                        $('#deploymentStatusLive').html('<i class=\"fas fa-info-circle\"></i> ⚠️ Deployment not running');
                        addLogToModal('warning', 'Company is not in deploying status');
                    }
                    
                    return; 
                }
                
                // Update status
                if (data.step) {
                    const alertType = data.error ? 'alert-danger' : 'alert-info';
                    $('#deploymentStatusLive').removeClass('alert-info alert-success alert-danger alert-warning').addClass(alertType);
                    $('#deploymentStatusLive').html('<i class=\"fas fa-cog fa-spin\"></i> ' + data.step);
                    
                    // Add to logs
                    addLogToModal(data.error ? 'error' : 'info', data.step);
                }
               
                if (data.progress >= 100 && !data.error) {
                    setTimeout(function() {
                        if (!deploymentFinished) {
                            deploymentFinished = true;
                            eventSource.close();
                            clearTimeout(forceCloseTimeout);
                            console.log('SSE stopped - 100% progress reached');
                        }
                    }, 2000); 
                }
                
            } catch (e) {
                console.error('❌ Error parsing SSE data:', e);
                addLogToModal('error', 'Failed to parse progress data');
            }
        };

        eventSource.onerror = function(event) {
            console.warn('⚠️ SSE connection error');            
          
            if (deploymentFinished) {
                console.log('Deployment finished, not reconnecting');
                eventSource.close();
                return;
            }            
           
            reconnectAttempts++;
            if (reconnectAttempts > maxReconnectAttempts) {
                deploymentFinished = true;
                eventSource.close();
                clearTimeout(forceCloseTimeout);
                console.log('🛑 Max reconnection attempts reached, stopping SSE');
                $('#deploymentStatusLive').removeClass().addClass('alert alert-danger');
                $('#deploymentStatusLive').html('<i class=\"fas fa-exclamation-triangle\"></i> Connection failed - too many retries');
                addLogToModal('error', 'Connection failed after ' + maxReconnectAttempts + ' attempts');
                return;
            }
            
            $('#deploymentStatusLive').removeClass('alert-success').addClass('alert-warning');
            $('#deploymentStatusLive').html('<i class=\"fas fa-exclamation-triangle\"></i> Connection issue - retrying (' + reconnectAttempts + '/' + maxReconnectAttempts + ')...');
            addLogToModal('warning', 'Connection issue, attempting to reconnect... (' + reconnectAttempts + '/' + maxReconnectAttempts + ')');
        };
        
    } else {
        $('#deploymentStatusLive').addClass('alert-warning');
        $('#deploymentStatusLive').html('<i class=\"fas fa-exclamation-triangle\"></i> SSE not supported by browser');
        addLogToModal('warning', 'Real-time monitoring not supported by this browser');
    }
}

// Helper function to add logs
function addLogToModal(type, message) {
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
    logDiv.append('<div style=\"color: ' + color + '; margin: 2px 0;\">[' + timestamp + '] ' + icon + ' ' + message + '</div>');
    logDiv.scrollTop(logDiv[0].scrollHeight);
}

// Global functions for debug modal (legacy support)
window.debugDeploymentFunctions = {
    updateProgress: function(progress, step) {
        const progressBar = $('#deploymentDebugModal').find('#progress-bar');
        const currentStep = $('#deploymentDebugModal').find('#current-step');

        if (progressBar.length) {
            progressBar.css('width', progress + '%');
            progressBar.attr('aria-valuenow', progress);
            progressBar.text(progress + '%');

            if (progress >= 100) {
                progressBar.removeClass('progress-bar-animated');
                progressBar.addClass('bg-success');
            }
        }

        if (step && currentStep.length) {
            currentStep.text(step);
        }
    },

    addLogEntry: function(type, message, output = null, timestamp = null) {
        const logsContainer = $('#deploymentDebugModal').find('#deployment-logs');
        if (!logsContainer.length) return;

        const logEntry = $('<div class=\"log-entry mb-2\"></div>');
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

        let entryHtml = '<span class=\"badge ' + badgeClass + '\">' + timeStr + '</span>' +
            '<i class=\"' + icon + '\"></i>' +
            '<span class=\"ml-2\">' + escapeHtml(message) + '</span>';
        
        if (output) {
            entryHtml += '<pre class=\"mt-2\">' + escapeHtml(output) + '</pre>';
        }

        logEntry.html(entryHtml);
        logsContainer.append(logEntry);
        logsContainer.scrollTop(logsContainer[0].scrollHeight);
    },

    showCompletion: function(success, message) {
        const completionDiv = $('#deploymentDebugModal').find('#completion-message');
        const completionText = $('#deploymentDebugModal').find('#completion-text');
        const completionActions = $('#deploymentDebugModal').find('#completion-actions');

        if (!completionDiv.length) return;

        completionDiv.removeClass('alert-success alert-danger');
        completionDiv.addClass('alert ' + (success ? 'alert-success' : 'alert-danger'));

        completionText.html('<i class=\"fas fa-' + (success ? 'check' : 'times') + '-circle\"></i> ' + message);
        
        if (success) {
            completionActions.html(
                '<button class=\"btn btn-primary\" onclick=\"$(\'#deploymentDebugModal\').modal(\'hide\'); location.reload();\">' +
                    '<i class=\"fas fa-arrow-left\"></i> Back to Companies List' +
                '</button>'
            );
        } else {
            completionActions.html(
                '<button class=\"btn btn-secondary\" onclick=\"$(\'#deploymentDebugModal\').modal(\'hide\');\">' +
                    '<i class=\"fas fa-arrow-left\"></i> Close' +
                '</button>' +
                '<button class=\"btn btn-warning ml-2\" onclick=\"$(\'#deploymentDebugModal\').modal(\'hide\'); location.reload();\">' +
                    '<i class=\"fas fa-redo\"></i> Retry Deployment' +
                '</button>'
            );
        }

        completionDiv.show();
    }
};

// Helper function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle cancel button click
$(document).on('click', '.cancel-action', function (e){
    e.preventDefault();
    $(this).closest('.action-details-row').find('.action-details').slideUp(700, function() {
        $(this).closest('.action-details-row').remove();
    });
});

// Watch for dynamically inserted content and force width constraints
$(document).on('DOMNodeInserted', '.action-details', function() {
    forceWidthConstraints();
});

// Force width constraints on window resize
$(window).on('resize', function() {
    forceWidthConstraints();
});

// Force width constraints on page load
$(document).ready(function() {
    forceWidthConstraints();
});

";

$this->registerJs($script, \yii\web\View::POS_END);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <?= Html::a('Create Company', ['create'], ['class' => 'btn btn-success']) ?>
                            <?= Html::button('<i class="fas fa-filter"></i> Filters', ['class' => 'btn btn-primary mobile-filter-btn']) ?>
                        </div>
                    </div>

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'filterModel' => $searchModel,
                        'tableOptions' => ['class' => 'table table-striped table-bordered'],
                        'columns' => [
                            ['class' => 'yii\grid\SerialColumn'],
                            'name',
                            'url:url',
                            [
                                'attribute' => 'status',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    return Html::tag('span', $model->getStatusName(), [
                                        'class' => 'badge ' . $model->getStatusBadgeClass()
                                    ]);
                                },
                                'filter' => Companies::getStatusList(),
                            ],
                            [
                                'label' => 'Administrator',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    if ($model->administrator) {
                                        return Html::encode($model->administrator->first_name . ' ' . $model->administrator->last_name)
                                            . '<br><small class="text-muted">' . Html::encode($model->administrator->email) . '</small>';
                                    }
                                    return '<span class="text-muted">Not assigned</span>';
                                },
                            ],
                            [
                                'class' => 'yii\grid\ActionColumn',
                                'template' => '{view} {update}',
                                'buttons' => [
                                    'view' => function ($url, $model, $key) {
                                        return Html::a(
                                            '<svg aria-hidden="hidden" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1.125em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M573 241C518 136 411 64 288 64S58 136 3 241a32 32 0 000 30c55 105 162 177 285 177s230-72 285-177a32 32 0 000-30zM288 400a144 144 0 11144-144 144 144 0 01-144 144zm0-240a95 95 0 00-25 4 48 48 0 01-67 67 96 96 0 1092-71z"></path></svg>',
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
                                ],
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