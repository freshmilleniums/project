<?php

namespace common\services;

use Yii;
use common\models\CourierStats;
use common\models\Tasks;
use common\models\Packages;
use common\models\Companies;
use backend\models\User;
use yii\db\Query;

/**
 * Service for dashboard statistics
 */
class DashboardStatisticsService
{
    /**
     * Get today's package statistics
     * @return array
     */
    public function getTodayPackageStats()
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;

        $added = Packages::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->count();

        $inProgress = Packages::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Packages::STATUS_IN_PROGRESS])
            ->count();

        $delivered = Packages::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Packages::STATUS_DELIVERED])
            ->count();

        $completed = Packages::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Packages::STATUS_COMPLETED])
            ->count();

        return [
            'added' => (int) $added,
            'in_progress' => (int) $inProgress,
            'delivered' => (int) $delivered,
            'completed' => (int) $completed,
            'in_progress_rate' => $added > 0 ? round(($inProgress / $added) * 100, 1) : 0,
            'delivered_rate' => $inProgress > 0 ? round(($delivered / $inProgress) * 100, 1) : 0,
            'completed_rate' => $delivered > 0 ? round(($completed / $delivered) * 100, 1) : 0,
        ];
    }

    /**
     * Get today's task statistics
     * @return array
     */
    public function getTodayTaskStats()
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;

        $added = Tasks::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->count();

        $inProgress = Tasks::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Tasks::STATUS_IN_PROGRESS])
            ->count();

        $delivered = Tasks::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Tasks::STATUS_DELIVERED])
            ->count();

        $completed = Tasks::find()
            ->where(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => Tasks::STATUS_COMPLETED])
            ->count();

        return [
            'added' => (int) $added,
            'in_progress' => (int) $inProgress,
            'delivered' => (int) $delivered,
            'completed' => (int) $completed,
            'in_progress_rate' => $added > 0 ? round(($inProgress / $added) * 100, 1) : 0,
            'delivered_rate' => $inProgress > 0 ? round(($delivered / $inProgress) * 100, 1) : 0,
            'completed_rate' => $delivered > 0 ? round(($completed / $delivered) * 100, 1) : 0,
        ];
    }

    /**
     * Get package statistics for date ranges
     * @return array
     */
    public function getPackageStatistics()
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;
        $weekStart = strtotime('monday this week');
        $monthStart = strtotime('first day of this month');

        return [
            'today' => array_merge(
                $this->getTodayPackageStats(),
                $this->getPackageStatsForPeriod($todayStart, $todayEnd)
            ),
            'week' => $this->getPackageStatsForPeriod($weekStart, $todayEnd),
            'month' => $this->getPackageStatsForPeriod($monthStart, $todayEnd),
        ];
    }

    /**
     * Get task statistics for date ranges
     * @return array
     */
    public function getTaskStatistics()
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;
        $weekStart = strtotime('monday this week');
        $monthStart = strtotime('first day of this month');

        return [
            'today' => array_merge(
                $this->getTodayTaskStats(),
                $this->getTaskStatsForPeriod($todayStart, $todayEnd)
            ),
            'week' => $this->getTaskStatsForPeriod($weekStart, $todayEnd),
            'month' => $this->getTaskStatsForPeriod($monthStart, $todayEnd),
        ];
    }

    /**
     * Get package statistics for specific period
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    private function getPackageStatsForPeriod($startTime, $endTime)
    {
        $statusCounts = (new Query())
            ->select(['status', 'COUNT(*) as count'])
            ->from('packages')
            ->where(['>=', 'created_at', $startTime])
            ->andWhere(['<=', 'created_at', $endTime])
            ->groupBy('status')
            ->all();

        $stats = [
            'total' => 0,
            'new' => 0,
            'in_progress' => 0,
            'delivered' => 0,
            'completed' => 0,
        ];

        foreach ($statusCounts as $statusCount) {
            $count = (int) $statusCount['count'];
            $stats['total'] += $count;

            switch ($statusCount['status']) {
                case Packages::STATUS_NEW:
                    $stats['new'] = $count;
                    break;
                case Packages::STATUS_IN_PROGRESS:
                    $stats['in_progress'] = $count;
                    break;
                case Packages::STATUS_DELIVERED:
                    $stats['delivered'] = $count;
                    break;
                case Packages::STATUS_COMPLETED:
                    $stats['completed'] = $count;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Get task statistics for specific period
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    private function getTaskStatsForPeriod($startTime, $endTime)
    {
        $statusCounts = (new Query())
            ->select(['status', 'COUNT(*) as count'])
            ->from('tasks')
            ->where(['>=', 'created_at', $startTime])
            ->andWhere(['<=', 'created_at', $endTime])
            ->groupBy('status')
            ->all();

        $stats = [
            'total' => 0,
            'new' => 0,
            'in_progress' => 0,
            'delivered' => 0,
            'completed' => 0,
        ];

        foreach ($statusCounts as $statusCount) {
            $count = (int) $statusCount['count'];
            $stats['total'] += $count;

            switch ($statusCount['status']) {
                case Tasks::STATUS_NEW:
                    $stats['new'] = $count;
                    break;
                case Tasks::STATUS_IN_PROGRESS:
                    $stats['in_progress'] = $count;
                    break;
                case Tasks::STATUS_DELIVERED:
                    $stats['delivered'] = $count;
                    break;
                case Tasks::STATUS_COMPLETED:
                    $stats['completed'] = $count;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Get courier statistics from CourierStats table
     * @return array
     */
    public function getCourierStatistics()
    {
        $todayStats = CourierStats::getTodayStats();
        $yesterdayStats = CourierStats::getYesterdayStats();
        $weekStats = $this->getCourierStatsForWeek();
        $monthStats = $this->getCourierStatsForMonth();

        return [
            'today' => $todayStats ? [
                'new_couriers' => $todayStats->new_couriers,
                'passed_test' => $todayStats->passed_test,
                'interviewed' => $todayStats->interviewed,
                'signed_contract' => $todayStats->signed_contract,
                'workers' => $todayStats->workers,
                'removed_by_reminders' => $todayStats->removed_by_reminders,
            ] : [
                'new_couriers' => 0,
                'passed_test' => 0,
                'interviewed' => 0,
                'signed_contract' => 0,
                'workers' => 0,
                'removed_by_reminders' => 0,
            ],
            'yesterday' => $yesterdayStats ? [
                'new_couriers' => $yesterdayStats->new_couriers,
                'passed_test' => $yesterdayStats->passed_test,
                'interviewed' => $yesterdayStats->interviewed,
                'signed_contract' => $yesterdayStats->signed_contract,
                'workers' => $yesterdayStats->workers,
                'removed_by_reminders' => $yesterdayStats->removed_by_reminders,
            ] : [
                'new_couriers' => 0,
                'passed_test' => 0,
                'interviewed' => 0,
                'signed_contract' => 0,
                'workers' => 0,
                'removed_by_reminders' => 0,
            ],
            'week' => $weekStats,
            'month' => $monthStats,
        ];
    }

    /**
     * Get courier statistics for current week
     * @return array
     */
    private function getCourierStatsForWeek()
    {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

        return $this->getCourierStatsForPeriod($weekStart, $weekEnd);
    }

    /**
     * Get courier statistics for current month
     * @return array
     */
    private function getCourierStatsForMonth()
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        return $this->getCourierStatsForPeriod($monthStart, $monthEnd);
    }

    /**
     * Get courier statistics for date period
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getCourierStatsForPeriod($startDate, $endDate)
    {
        $stats = (new Query())
            ->select([
                'SUM(new_couriers) as new_couriers',
                'SUM(passed_test) as passed_test',
                'SUM(interviewed) as interviewed',
                'SUM(signed_contract) as signed_contract',
                'SUM(workers) as workers',
                'SUM(removed_by_reminders) as removed_by_reminders',
            ])
            ->from('courier_stats')
            ->where(['>=', 'date', $startDate])
            ->andWhere(['<=', 'date', $endDate])
            ->one();

        return [
            'new_couriers' => (int) ($stats['new_couriers'] ?? 0),
            'passed_test' => (int) ($stats['passed_test'] ?? 0),
            'interviewed' => (int) ($stats['interviewed'] ?? 0),
            'signed_contract' => (int) ($stats['signed_contract'] ?? 0),
            'workers' => (int) ($stats['workers'] ?? 0),
            'removed_by_reminders' => (int) ($stats['removed_by_reminders'] ?? 0),
        ];
    }

    /**
     * Get CRM (Companies) statistics
     * @return array
     */
    public function getCrmStatistics()
    {
        $activeCrm = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->count();

        $inactiveCrm = Companies::find()
            ->where(['!=', 'status', Companies::STATUS_RUNNING])
            ->count();

        $totalCouriers = User::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where(['auth_assignment.item_name' => 'courier'])
            ->count();

        return [
            'active_crm' => $activeCrm,
            'inactive_crm' => $inactiveCrm,
            'total_couriers' => $totalCouriers,
        ];
    }

    /**
     * Get company statistics with courier breakdown
     * @return array
     */
    public function getCompanyStatistics()
    {
        $periods = ['today', 'yesterday', 'week', 'month'];
        $stats = [];

        foreach ($periods as $period) {
            $stats[$period] = [];

            // Get all companies
            $companies = Companies::find()->all();

            foreach ($companies as $company) {
                $companyStats = $this->getCompanyPeriodStats($company->id, $period);

                $stats[$period][] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'new_couriers' => $companyStats['new_couriers'],
                    'passed_test' => $companyStats['passed_test'],
                    'interviewed' => $companyStats['interviewed'],
                    'signed_contract' => $companyStats['signed_contract'],
                    'workers' => $companyStats['workers'],
                    'removed_by_reminders' => $companyStats['removed_by_reminders'],
                ];
            }
        }

        return $stats;
    }

    /**
     * Get company courier statistics for specific period
     * @param int $companyId
     * @param string $period
     * @return array
     */
    private function getCompanyPeriodStats($companyId, $period)
    {
        list($startTime, $endTime) = $this->getPeriodTimeRange($period);

        $baseQuery = User::find()
            ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
            ->where([
                'auth_assignment.item_name' => 'courier',
                'user.company_id' => $companyId,
            ]);

        // New couriers (registered in period)
        $newCouriers = (clone $baseQuery)
            ->andWhere(['>=', 'user.created_at', $startTime])
            ->andWhere(['<=', 'user.created_at', $endTime])
            ->count();

        // Passed test (moved to TEST_VERIFIED status in period)
        $passedTest = (clone $baseQuery)
            ->andWhere(['user.substatus' => User::SUBSTATUS_FINAL_ASSIGNMENT])
            ->andWhere(['>=', 'user.updated_at', $startTime])
            ->andWhere(['<=', 'user.updated_at', $endTime])
            ->count();

        // Interviewed
        $interviewed = (clone $baseQuery)
            ->andWhere(['user.substatus' => User::SUBSTATUS_INTERVIEW_COMPLETED])
            ->andWhere(['>=', 'user.updated_at', $startTime])
            ->andWhere(['<=', 'user.updated_at', $endTime])
            ->count();

        // Signed contract
        $signedContract = (clone $baseQuery)
            ->andWhere(['user.substatus' => User::SUBSTATUS_CONTRACT_SENT])
            ->andWhere(['>=', 'user.updated_at', $startTime])
            ->andWhere(['<=', 'user.updated_at', $endTime])
            ->count();

        // Workers
        $workers = (clone $baseQuery)
            ->andWhere(['user.substatus' => User::SUBSTATUS_ACTIVE_EMPLOYEE])
            ->andWhere(['>=', 'user.updated_at', $startTime])
            ->andWhere(['<=', 'user.updated_at', $endTime])
            ->count();

        // Removed by reminders (archived)
        $removedByReminders = (clone $baseQuery)
            ->andWhere(['user.substatus' => User::SUBSTATUS_ARCHIVED])
            ->andWhere(['>=', 'user.updated_at', $startTime])
            ->andWhere(['<=', 'user.updated_at', $endTime])
            ->count();

        return [
            'new_couriers' => $newCouriers,
            'passed_test' => $passedTest,
            'interviewed' => $interviewed,
            'signed_contract' => $signedContract,
            'workers' => $workers,
            'removed_by_reminders' => $removedByReminders,
        ];
    }

    /**
     * Get time range for period
     * @param string $period
     * @return array
     */
    private function getPeriodTimeRange($period)
    {
        switch ($period) {
            case 'today':
                return [strtotime('today'), strtotime('tomorrow') - 1];
            case 'yesterday':
                return [strtotime('yesterday'), strtotime('today') - 1];
            case 'week':
                return [strtotime('monday this week'), time()];
            case 'month':
                return [strtotime('first day of this month'), time()];
            default:
                return [strtotime('today'), strtotime('tomorrow') - 1];
        }
    }

    /**
     * Update courier statistics when user status changes
     * @param int $newStatus
     */
    public static function updateCourierStatsOnStatusChange($newStatus)
    {
        switch ($newStatus) {
            case User::SUBSTATUS_WAITING_FOR_CALL:
                CourierStats::incrementTodayStats('new_couriers');
                break;
            case User::SUBSTATUS_TEST_VERIFIED:
                CourierStats::incrementTodayStats('passed_test');
                break;
            case User::SUBSTATUS_INTERVIEWED:
                CourierStats::incrementTodayStats('interviewed');
                break;
            case User::SUBSTATUS_SIGNED_CONTRACT:
                CourierStats::incrementTodayStats('signed_contract');
                break;
            case User::SUBSTATUS_WORKER:
                CourierStats::incrementTodayStats('workers');
                break;
        }
    }

    /**
     * Update courier statistics when user is removed by reminders
     */
    public static function updateCourierStatsOnReminderRemoval()
    {
        CourierStats::incrementTodayStats('removed_by_reminders');
    }

    /**
     * Calculate percentage change between two values
     * @param int $current
     * @param int $previous
     * @return float
     */
    public function calculatePercentageChange($current, $previous)
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
    public function getChangeClass($change)
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
    public function getChangeIcon($change)
    {
        if ($change > 0) return 'fas fa-arrow-up';
        if ($change < 0) return 'fas fa-arrow-down';
        return 'fas fa-minus';
    }
}