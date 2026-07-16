<?php

/** @var yii\web\View $this */
/** @var array $crmStats */
/** @var array $employeeStatusCounts */
/** @var array $taskStats */
/** @var array $administratorsOverview */
/** @var array $callCenterLoad */
/** @var array $trainingSummary */
/** @var array $unassignedResources */
/** @var \common\services\EmployersDashboardStatisticsService $statsService */

use yii\helpers\Html;

$this->title = 'Dashboard Super Administrator';

$totalEmployees = array_sum(array_column($employeeStatusCounts, 'count'));
?>

<style>
    .stats-table {
        background: white;
        border-radius: 0.375rem;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stats-table .table-header {
        background: #6c757d;
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .stats-table .table {
        margin-bottom: 0;
    }

    .stats-table .table thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        color: #495057;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.75rem;
        vertical-align: middle;
    }

    .stats-table .table tbody td {
        padding: 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f3f4;
    }

    .stats-table .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .stats-rate {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .summary-row {
        background: #f8f9fa !important;
        border-top: 2px solid #6c757d !important;
    }

    .summary-row td {
        color: #495057;
        font-weight: 600;
        border-bottom: none !important;
    }

    .stuck-badge {
        background-color: #ffc107;
        color: #212529;
    }

    .progress-bar-load {
        height: 6px;
        border-radius: 3px;
    }

    /* Desktop/Mobile table switching */
    .period-stats-mobile,
    .company-stats-mobile {
        display: none;
    }

    .period-stat-card,
    .admin-stat-card {
        border-bottom: 1px solid #dee2e6;
        padding: 12px 16px;
    }

    .period-stat-card:last-child,
    .admin-stat-card:last-child {
        border-bottom: none;
    }

    .period-stat-header,
    .admin-stat-header {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .admin-stat-subheader {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 6px;
    }

    .period-stat-grid,
    .admin-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .period-stat-item,
    .admin-stat-item {
        text-align: center;
    }

    .period-stat-label,
    .admin-stat-label {
        font-size: 0.7rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .period-stat-value,
    .admin-stat-value {
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
    }

    @media screen and (max-width: 768px) {
        .period-stats-desktop,
        .company-stats-desktop {
            display: none;
        }

        .period-stats-mobile,
        .company-stats-mobile {
            display: block;
        }
    }
</style>

<div class="site-index">
    <div class="body-content">

        <!-- Block 1: CRM Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="fas fa-building"></i> CRM & Employees Overview</h5>
            </div>
            <div class="col-lg-4 col-6 dashboard-info-box-col">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-server"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active CRM</span>
                        <span class="info-box-number"><?= $crmStats['active_crm'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6 dashboard-info-box-col">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-power-off"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive CRM</span>
                        <span class="info-box-number"><?= $crmStats['inactive_crm'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6 dashboard-info-box-col">
                <div class="info-box">
                    <span class="info-box-icon bg-secondary"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Employees</span>
                        <span class="info-box-number"><?= $crmStats['total_employees'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block 2: Employees by Status -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="fas fa-users"></i> Employees by Status</h5>
            </div>
            <?php foreach ($employeeStatusCounts as $data): ?>
                <div class="col-lg-3 col-6 dashboard-info-box-col">
                    <div class="info-box">
                        <span class="info-box-icon bg-secondary"><i class="fas fa-user"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?= $data['label'] ?></span>
                            <span class="info-box-number"><?= $data['count'] ?></span>
                            <?php if ($totalEmployees > 0): ?>
                                <div class="stats-rate"><?= round($data['count'] / $totalEmployees * 100, 1) ?>% of total</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Block 3: Task Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-table">
                    <div class="table-header">
                        <i class="fas fa-tasks"></i> Task Statistics
                    </div>

                    <!-- Desktop -->
                    <div class="period-stats-desktop">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Period</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">New</th>
                                    <th class="text-center">In Progress</th>
                                    <th class="text-center">Completed</th>
                                    <th class="text-center">On Hold</th>
                                    <th class="text-center">Cancelled</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'] as $period => $label): ?>
                                    <tr>
                                        <td><strong><?= $label ?></strong></td>
                                        <td class="text-center"><?= $taskStats[$period]['total'] ?></td>
                                        <td class="text-center"><?= $taskStats[$period]['new'] ?></td>
                                        <td class="text-center"><?= $taskStats[$period]['in_progress'] ?></td>
                                        <td class="text-center"><?= $taskStats[$period]['completed'] ?></td>
                                        <td class="text-center"><?= $taskStats[$period]['on_hold'] ?></td>
                                        <td class="text-center"><?= $taskStats[$period]['cancelled'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="period-stats-mobile">
                        <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'] as $period => $label): ?>
                            <div class="period-stat-card">
                                <div class="period-stat-header"><?= $label ?></div>
                                <div class="period-stat-grid">
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">Total</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['total'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">New</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['new'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">In Progress</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['in_progress'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">Completed</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['completed'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">On Hold</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['on_hold'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">Cancelled</div>
                                        <div class="period-stat-value"><?= $taskStats[$period]['cancelled'] ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block 4: Training Progress -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-table">
                    <div class="table-header">
                        <i class="fas fa-graduation-cap"></i> Training Progress
                        <?php if ($trainingSummary['stuck_count'] > 0): ?>
                            <span class="badge stuck-badge ml-2"><?= $trainingSummary['stuck_count'] ?> stuck (24h+)</span>
                        <?php endif; ?>
                    </div>

                    <!-- Desktop -->
                    <div class="period-stats-desktop">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Module</th>
                                    <th class="text-center">Employees</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($trainingSummary['by_module'])): ?>
                                    <tr>
                                        <td colspan="2" class="text-muted text-center">No employees currently in training</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($trainingSummary['by_module'] as $module): ?>
                                        <tr>
                                            <td><?= Html::encode($module['title']) ?></td>
                                            <td class="text-center"><?= $module['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <tr class="summary-row">
                                    <td><strong>Total in Training</strong></td>
                                    <td class="text-center"><strong><?= $trainingSummary['total_in_training'] ?></strong></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="period-stats-mobile">
                        <?php if ($trainingSummary['total_in_training'] === 0): ?>
                            <div class="period-stat-card">
                                <span class="text-muted">No employees currently in training</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trainingSummary['by_module'] as $module): ?>
                                <?php if ($module['count'] > 0): ?>
                                    <div class="period-stat-card">
                                        <div class="period-stat-header"><?= Html::encode($module['title']) ?></div>
                                        <div class="period-stat-grid" style="grid-template-columns: repeat(1, 1fr);">
                                            <div class="period-stat-item" style="text-align: left;">
                                                <div class="period-stat-label">Employees on this module</div>
                                                <div class="period-stat-value"><?= $module['count'] ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block 5: Administrators Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-table">
                    <div class="table-header">
                        <i class="fas fa-user-tie"></i> Administrators Overview
                    </div>

                    <!-- Desktop -->
                    <div class="company-stats-desktop">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Administrator</th>
                                    <th class="text-center">Total</th>
                                    <?php foreach ($employeeStatusCounts as $data): ?>
                                        <th class="text-center"><?= $data['label'] ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($administratorsOverview)): ?>
                                    <tr>
                                        <td colspan="100%" class="text-muted text-center">No administrators found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($administratorsOverview as $admin): ?>
                                        <tr>
                                            <td><strong><?= Html::encode($admin['name']) ?></strong></td>
                                            <td class="text-center"><strong><?= $admin['total_employees'] ?></strong></td>
                                            <?php foreach ($admin['status_counts'] as $data): ?>
                                                <td class="text-center"><?= $data['count'] ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="company-stats-mobile">
                        <?php if (empty($administratorsOverview)): ?>
                            <div class="period-stat-card">
                                <span class="text-muted">No administrators found</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($administratorsOverview as $admin): ?>
                                <div class="admin-stat-card">
                                    <div class="admin-stat-header"><?= Html::encode($admin['name']) ?></div>
                                    <div class="admin-stat-subheader">Total employees: <strong><?= $admin['total_employees'] ?></strong></div>
                                    <div class="admin-stat-grid">
                                        <?php foreach ($admin['status_counts'] as $data): ?>
                                            <div class="admin-stat-item">
                                                <div class="admin-stat-label"><?= $data['label'] ?></div>
                                                <div class="admin-stat-value"><?= $data['count'] ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block 6: Call Center Operators Load + Unassigned Resources -->
        <div class="row mb-4">
            <div class="col-lg-7 col-12 mb-4">
                <div class="stats-table">
                    <div class="table-header">
                        <i class="fas fa-headset"></i> Call Center Operators Load
                    </div>

                    <!-- Desktop -->
                    <div class="period-stats-desktop">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Operator</th>
                                    <th class="text-center">Assigned</th>
                                    <th class="text-center">% of Total</th>
                                    <th>Load</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($callCenterLoad['operators'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted text-center">No phone operators found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($callCenterLoad['operators'] as $operator): ?>
                                        <tr>
                                            <td><?= Html::encode($operator['name']) ?></td>
                                            <td class="text-center"><?= $operator['assigned_count'] ?></td>
                                            <td class="text-center"><?= $operator['percent'] ?>%</td>
                                            <td>
                                                <div class="progress progress-bar-load">
                                                    <div class="progress-bar bg-secondary" style="width: <?= $operator['percent'] ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <tr class="summary-row">
                                    <td><strong>Total Employees</strong></td>
                                    <td class="text-center"><strong><?= $callCenterLoad['total_candidates'] ?></strong></td>
                                    <td class="text-center" colspan="2">
                                        <?php if ($callCenterLoad['unassigned'] > 0): ?>
                                            <span class="text-danger"><?= $callCenterLoad['unassigned'] ?> unassigned</span>
                                        <?php else: ?>
                                            <span class="text-success">All assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="period-stats-mobile">
                        <?php if (empty($callCenterLoad['operators'])): ?>
                            <div class="period-stat-card">
                                <span class="text-muted">No phone operators found</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($callCenterLoad['operators'] as $operator): ?>
                                <div class="period-stat-card">
                                    <div class="period-stat-header"><?= Html::encode($operator['name']) ?></div>
                                    <div class="period-stat-grid">
                                        <div class="period-stat-item">
                                            <div class="period-stat-label">Assigned</div>
                                            <div class="period-stat-value"><?= $operator['assigned_count'] ?></div>
                                        </div>
                                        <div class="period-stat-item">
                                            <div class="period-stat-label">% of Total</div>
                                            <div class="period-stat-value"><?= $operator['percent'] ?>%</div>
                                        </div>
                                    </div>
                                    <div class="progress progress-bar-load mt-2">
                                        <div class="progress-bar bg-secondary" style="width: <?= $operator['percent'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="period-stat-card">
                                <div class="period-stat-header">Total</div>
                                <div class="period-stat-grid">
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">All Employees</div>
                                        <div class="period-stat-value"><?= $callCenterLoad['total_candidates'] ?></div>
                                    </div>
                                    <div class="period-stat-item">
                                        <div class="period-stat-label">Unassigned</div>
                                        <div class="period-stat-value <?= $callCenterLoad['unassigned'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= $callCenterLoad['unassigned'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Unassigned Resources -->
            <div class="col-lg-5 col-12 mb-4">
                <div class="row">
                    <div class="col-6 dashboard-info-box-col">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary"><i class="fas fa-hand-holding-usd"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Unassigned Investors</span>
                                <span class="info-box-number"><?= $unassignedResources['unassigned_investors'] ?></span>
                                <div class="stats-rate">of <?= $unassignedResources['total_investors'] ?> total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 dashboard-info-box-col">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary"><i class="fas fa-project-diagram"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Unassigned Projects</span>
                                <span class="info-box-number"><?= $unassignedResources['unassigned_projects'] ?></span>
                                <div class="stats-rate">of <?= $unassignedResources['total_projects'] ?> total</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>