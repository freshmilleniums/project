<?php

namespace common\services;

use Yii;
use yii\db\Query;
use backend\models\User;
use backend\models\TrainingModule;
use backend\models\UserTrainingProgress;
use common\models\Investor;
use common\models\Project;
use common\models\ScheduledCall;
use common\models\Task;
use common\models\Companies;

/**
 * Service for dashboard statistics across all roles in Employers CRM
 */
class EmployersDashboardStatisticsService
{
    /**
     * Employee status groups (mirrors UserService::EMPLOYEE_STATUS_GROUPS)
     */
    private const EMPLOYEE_STATUS_GROUPS = [
        'not_processed' => [
            'label' => 'Not Processed',
            'substatuses' => [User::SUBSTATUS_NOT_PROCESSED],
        ],
        'new_applicants' => [
            'label' => 'New Applicants',
            'substatuses' => [User::SUBSTATUS_NEW_APPLICANT],
        ],
        'primary_contact' => [
            'label' => 'Primary Contact',
            'substatuses' => [User::SUBSTATUS_PRIMARY_CONTACT],
        ],
        'interview_completed' => [
            'label' => 'Interview Completed',
            'substatuses' => [User::SUBSTATUS_INTERVIEW_COMPLETED],
        ],
        'contract_sent' => [
            'label' => 'Contract Sent',
            'substatuses' => [User::SUBSTATUS_CONTRACT_SENT],
        ],
        'training' => [
            'label' => 'Training',
            'substatuses' => [
                User::SUBSTATUS_TRAINING_IN_PROGRESS,
                User::SUBSTATUS_FINAL_ASSIGNMENT,
                User::SUBSTATUS_UNCOMPLETED_TASK,
            ],
        ],
        'active_employees' => [
            'label' => 'Active Employees',
            'substatuses' => [User::SUBSTATUS_ACTIVE_EMPLOYEE],
        ],
        'refused' => [
            'label' => 'Refused',
            'substatuses' => [User::SUBSTATUS_CONTRACT_REFUSED],
        ],
        'archived' => [
            'label' => 'Archived',
            'substatuses' => [User::SUBSTATUS_ARCHIVED],
        ],
    ];

    /**
     * Base query for active employees
     * @return \yii\db\ActiveQuery
     */
    private function getEmployeeBaseQuery()
    {
        return User::find()
            ->alias('user')
            ->innerJoin('auth_assignment aa', 'aa.user_id = user.id')
            ->where(['aa.item_name' => 'employee'])
            ->andWhere(['user.status' => User::STATUS_ACTIVE]);
    }

    /**
     * Get employee counts grouped by status group
     *
     * @param int|null $administratorId Filter by administrator (for admin dashboard)
     * @param int|null $callCenterOperatorId Filter by call center operator (for phone-operator dashboard)
     * @return array [groupKey => ['label' => ..., 'count' => ...]]
     */
    public function getEmployeeStatusCounts(?int $administratorId = null, ?int $callCenterOperatorId = null): array
    {
        $counts = [];

        foreach (self::EMPLOYEE_STATUS_GROUPS as $groupKey => $groupData) {
            $query = $this->getEmployeeBaseQuery()
                ->andWhere(['user.substatus' => $groupData['substatuses']]);

            if ($administratorId !== null) {
                $query->andWhere(['user.administrator_id' => $administratorId]);
            }

            if ($callCenterOperatorId !== null) {
                $query->andWhere(['user.call_center_operator_id' => $callCenterOperatorId]);
            }

            $counts[$groupKey] = [
                'label' => $groupData['label'],
                'count' => (int) $query->count(),
            ];
        }

        return $counts;
    }

    /**
     * Get task statistics for today/week/month with optional filters
     *
     * @param array $filters Possible keys: 'employee_ids' (array), 'created_by', 'assigned_to'
     * @return array
     */
    public function getTaskStatistics(array $filters = []): array
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;
        $weekStart = strtotime('monday this week');
        $monthStart = strtotime('first day of this month');

        return [
            'today' => $this->getTaskStatsForPeriod($todayStart, $todayEnd, $filters),
            'week' => $this->getTaskStatsForPeriod($weekStart, $todayEnd, $filters),
            'month' => $this->getTaskStatsForPeriod($monthStart, $todayEnd, $filters),
        ];
    }

    /**
     * Get task statistics for a specific period
     * @param int $start
     * @param int $end
     * @param array $filters
     * @return array
     */
    private function getTaskStatsForPeriod(int $start, int $end, array $filters): array
    {
        $query = Task::find()
            ->andWhere(['>=', 'created_at', $start])
            ->andWhere(['<=', 'created_at', $end]);

        // Use array_key_exists so an empty array (administrator with no employees yet)
        // correctly results in zero matching tasks, instead of being ignored
        // and returning stats for all tasks in the system.
        if (array_key_exists('employee_ids', $filters)) {
            $query->andWhere(['assigned_to' => $filters['employee_ids']]);
        }

        if (!empty($filters['created_by'])) {
            $query->andWhere(['created_by' => $filters['created_by']]);
        }

        if (!empty($filters['assigned_to'])) {
            $query->andWhere(['assigned_to' => $filters['assigned_to']]);
        }

        $statusCounts = (clone $query)
            ->select(['status', 'COUNT(*) as cnt'])
            ->groupBy('status')
            ->asArray()
            ->all();

        $stats = [
            'total' => 0,
            'new' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'on_hold' => 0,
            'cancelled' => 0,
        ];

        foreach ($statusCounts as $row) {
            $cnt = (int) $row['cnt'];
            $stats['total'] += $cnt;

            switch ((int) $row['status']) {
                case Task::STATUS_NEW:
                    $stats['new'] = $cnt;
                    break;
                case Task::STATUS_IN_PROGRESS:
                    $stats['in_progress'] = $cnt;
                    break;
                case Task::STATUS_COMPLETED:
                    $stats['completed'] = $cnt;
                    break;
                case Task::STATUS_ON_HOLD:
                    $stats['on_hold'] = $cnt;
                    break;
                case Task::STATUS_CANCELLED:
                    $stats['cancelled'] = $cnt;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Get overview of administrators with their employee status breakdown
     * @return array
     */
    public function getAdministratorsOverview(): array
    {
        $admins = User::getAdministratorsList();
        $overview = [];

        foreach ($admins as $admin) {
            $statusCounts = $this->getEmployeeStatusCounts((int) $admin['id']);
            $totalEmployees = array_sum(array_column($statusCounts, 'count'));

            $overview[] = [
                'id' => $admin['id'],
                'name' => trim($admin['first_name'] . ' ' . $admin['last_name']),
                'total_employees' => $totalEmployees,
                'status_counts' => $statusCounts,
            ];
        }

        return $overview;
    }

    /**
     * Get call center operators load distribution
     * @return array
     */
    public function getCallCenterOperatorsLoad(): array
    {
        $operators = User::getPhoneOperatorsList();
        $totalCandidates = $this->getEmployeeBaseQuery()->count();
        $load = [];

        foreach ($operators as $operator) {
            $count = $this->getEmployeeBaseQuery()
                ->andWhere(['user.call_center_operator_id' => $operator['id']])
                ->count();

            $load[] = [
                'id' => $operator['id'],
                'name' => trim($operator['first_name'] . ' ' . $operator['last_name']),
                'assigned_count' => (int) $count,
                'percent' => $totalCandidates > 0 ? round($count / $totalCandidates * 100, 1) : 0,
            ];
        }

        $unassigned = $this->getEmployeeBaseQuery()
            ->andWhere(['user.call_center_operator_id' => null])
            ->count();

        return [
            'operators' => $load,
            'total_candidates' => (int) $totalCandidates,
            'unassigned' => (int) $unassigned,
        ];
    }

    /**
     * Get training progress summary across all candidates currently in training
     *
     * @param int|null $administratorId Filter by administrator
     * @return array
     */
    public function getTrainingProgressSummary(?int $administratorId = null): array
    {
        $modules = TrainingModule::find()
            ->where(['is_active' => 1])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $query = UserTrainingProgress::find()
            ->alias('utp')
            ->innerJoin('user u', 'u.id = utp.user_id')
            ->andWhere(['u.substatus' => User::SUBSTATUS_TRAINING_IN_PROGRESS])
            ->andWhere(['u.status' => User::STATUS_ACTIVE]);

        if ($administratorId !== null) {
            $query->andWhere(['u.administrator_id' => $administratorId]);
        }

        $progressRows = $query->all();

        $byModule = [];
        foreach ($modules as $module) {
            $byModule[$module->id] = [
                'title' => $module->title,
                'sort' => $module->sort,
                'count' => 0,
            ];
        }

        // Candidates with no activity for more than 24 hours are considered "stuck"
        $stuckThreshold = time() - (24 * 3600);
        $stuckCount = 0;

        foreach ($progressRows as $row) {
            if (isset($byModule[$row->current_module_id])) {
                $byModule[$row->current_module_id]['count']++;
            }

            if ($row->last_attempt_at && $row->last_attempt_at < $stuckThreshold) {
                $stuckCount++;
            }
        }

        return [
            'by_module' => array_values($byModule),
            'total_in_training' => count($progressRows),
            'stuck_count' => $stuckCount,
        ];
    }

    /**
     * Get training progress for a single user (employee dashboard)
     * @param int $userId
     * @return array
     */
    public function getTrainingProgress(int $userId): array
    {
        $progress = UserTrainingProgress::findOne(['user_id' => $userId]);
        $totalModules = (int) TrainingModule::find()->where(['is_active' => 1])->count();

        if (!$progress) {
            return [
                'current_module' => null,
                'completed' => 0,
                'total' => $totalModules,
                'percent' => 0,
                'attempts' => 0,
                'last_score' => 0,
            ];
        }

        $completedCount = count($progress->getCompletedModulesArray());

        return [
            'current_module' => $progress->currentModule,
            'completed' => $completedCount,
            'total' => $totalModules,
            'percent' => $totalModules > 0 ? round($completedCount / $totalModules * 100) : 0,
            'attempts' => $progress->current_module_attempts,
            'last_score' => $progress->last_attempt_score,
        ];
    }

    /**
     * Get scheduled calls info for an operator (phone-operator dashboard)
     * @param int $operatorId
     * @return array
     */
    public function getScheduledCallsForOperator(int $operatorId): array
    {
        $upcoming = ScheduledCall::getUpcoming($operatorId);

        $totalPending = ScheduledCall::find()
            ->where(['operator_id' => $operatorId, 'is_done' => 0])
            ->count();

        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;

        $todayCount = ScheduledCall::find()
            ->where(['operator_id' => $operatorId])
            ->andWhere(['>=', 'scheduled_at', $todayStart])
            ->andWhere(['<=', 'scheduled_at', $todayEnd])
            ->count();

        $doneToday = ScheduledCall::find()
            ->where(['operator_id' => $operatorId, 'is_done' => 1])
            ->andWhere(['>=', 'updated_at', $todayStart])
            ->andWhere(['<=', 'updated_at', $todayEnd])
            ->count();

        return [
            'upcoming' => $upcoming,
            'total_pending' => (int) $totalPending,
            'today_total' => (int) $todayCount,
            'today_done' => (int) $doneToday,
        ];
    }

    /**
     * Get investors and current project summary for an employee
     * @param int $employeeId
     * @return array
     */
    public function getInvestorProjectSummary(int $employeeId): array
    {
        $investors = Investor::find()
            ->alias('i')
            ->innerJoin('investor_employee ie', 'ie.investor_id = i.id')
            ->where(['ie.employee_id' => $employeeId])
            ->limit(5)
            ->all();

        $project = Project::find()
            ->where(['employee_id' => $employeeId])
            ->one();

        return [
            'investors' => $investors,
            'project' => $project,
        ];
    }

    /**
     * Get investors without assigned employee and projects without assigned employee
     * @return array
     */
    public function getUnassignedInvestorsAndProjects(): array
    {
        $unassignedInvestors = Investor::find()
            ->alias('i')
            ->leftJoin('investor_employee ie', 'ie.investor_id = i.id')
            ->where(['ie.investor_id' => null])
            ->count();

        $unassignedProjects = Project::find()
            ->where(['employee_id' => null])
            ->count();

        $totalInvestors = Investor::find()->count();
        $totalProjects = Project::find()->count();

        return [
            'unassigned_investors' => (int) $unassignedInvestors,
            'total_investors' => (int) $totalInvestors,
            'unassigned_projects' => (int) $unassignedProjects,
            'total_projects' => (int) $totalProjects,
        ];
    }

    /**
     * Calculate percentage change between two values
     * @param int $current
     * @param int $previous
     * @return float
     */
    public function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get CSS class for change indicator
     * @param float $change
     * @return string
     */
    public function getChangeClass(float $change): string
    {
        if ($change > 0) return 'text-success';
        if ($change < 0) return 'text-danger';
        return 'text-muted';
    }

    /**
     * Get icon for change indicator
     * @param float $change
     * @return string
     */
    public function getChangeIcon(float $change): string
    {
        if ($change > 0) return 'fas fa-arrow-up';
        if ($change < 0) return 'fas fa-arrow-down';
        return 'fas fa-minus';
    }

    public function getCrmStatistics(): array
    {
        $activeCrm = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->count();

        $inactiveCrm = Companies::find()
            ->where(['!=', 'status', Companies::STATUS_RUNNING])
            ->count();

        $totalEmployees = $this->getEmployeeBaseQuery()->count();

        return [
            'active_crm'      => (int) $activeCrm,
            'inactive_crm'    => (int) $inactiveCrm,
            'total_employees' => (int) $totalEmployees,
        ];
    }
}