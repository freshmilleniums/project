<?php

namespace common\services;

use Yii;
use common\models\UrgentCall;
use backend\models\User;
use yii\db\Query;

/**
 * Service for call statistics
 */
class CallStatisticsService
{
    /**
     * Get today's call statistics summary
     * @return array
     */
    public function getTodayCallStatistics(): array
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;

        // Total calls today
        $totalCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $todayStart])
            ->where(['<=', 'created_at', $todayEnd])
            ->count();

        // Calls that need to be called (unanswered)
        $unansweredCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $todayStart])
            ->where(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        // Answered calls
        $answeredCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $todayStart])
            ->where(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_CALLED])
            ->count();

        $answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;

        return [
            'total_calls' => (int) $totalCalls,
            'unanswered_calls' => (int) $unansweredCalls,
            'answered_calls' => (int) $answeredCalls,
            'answer_rate' => $answerRate
        ];
    }

    /**
     * Get yesterday's call statistics summary
     * @return array
     */
    public function getYesterdayCallStatistics(): array
    {
        $yesterdayStart = strtotime('yesterday');
        $yesterdayEnd = strtotime('today') - 1;

        // Total calls yesterday
        $totalCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $yesterdayStart])
            ->where(['<=', 'created_at', $yesterdayEnd])
            ->count();

        // Calls that needed to be called (unanswered)
        $unansweredCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $yesterdayStart])
            ->where(['<=', 'created_at', $yesterdayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        // Answered calls
        $answeredCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $yesterdayStart])
            ->where(['<=', 'created_at', $yesterdayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_CALLED])
            ->count();

        $answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;

        return [
            'total_calls' => (int) $totalCalls,
            'unanswered_calls' => (int) $unansweredCalls,
            'answered_calls' => (int) $answeredCalls,
            'answer_rate' => $answerRate
        ];
    }

    /**
     * Get detailed call statistics by call center operators
     * @return array
     */
    public function getOperatorCallStatistics(): array
    {
        // Get all call center operators
        $callCenterOperators = User::find()
            ->alias('u')
            ->innerJoin('auth_assignment aa', 'aa.user_id = u.id')
            ->where(['aa.item_name' => 'call-operator'])
            ->andWhere(['u.status' => User::STATUS_ACTIVE])
            ->all();

        $statistics = [];

        foreach ($callCenterOperators as $operator) {
            $operatorStats = $this->getOperatorStats($operator->id);
            $operatorStats['name'] = trim($operator->first_name . ' ' . $operator->last_name);
            $operatorStats['operator_id'] = $operator->id;
            $statistics[] = $operatorStats;
        }

        return $statistics;
    }

    /**
     * Get call statistics for specific operator
     * @param int $operatorId
     * @return array
     */
    private function getOperatorStats(int $operatorId): array
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;
        $yesterdayStart = strtotime('yesterday');
        $yesterdayEnd = strtotime('today') - 1;
        $monthStart = strtotime('first day of this month');
        $monthEnd = strtotime('last day of this month 23:59:59');

        // Today's statistics
        $todayCalls = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->count();

        $todayUnanswered = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $todayStart])
            ->andWhere(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        // Yesterday's statistics
        $yesterdayCalls = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $yesterdayStart])
            ->andWhere(['<=', 'created_at', $yesterdayEnd])
            ->count();

        $yesterdayUnanswered = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $yesterdayStart])
            ->andWhere(['<=', 'created_at', $yesterdayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        // Month's statistics
        $monthCalls = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $monthStart])
            ->andWhere(['<=', 'created_at', $monthEnd])
            ->count();

        $monthUnanswered = UrgentCall::find()
            ->where(['call_center_employee_id' => $operatorId])
            ->andWhere(['>=', 'created_at', $monthStart])
            ->andWhere(['<=', 'created_at', $monthEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        return [
            'today_calls' => (int) $todayCalls,
            'today_unanswered' => (int) $todayUnanswered,
            'yesterday_calls' => (int) $yesterdayCalls,
            'yesterday_unanswered' => (int) $yesterdayUnanswered,
            'month_calls' => (int) $monthCalls,
            'month_unanswered' => (int) $monthUnanswered
        ];
    }

    /**
     * Get calls assigned to call center but not handled yet (all operators)
     * @return array
     */
    public function getUnassignedCallsStatistics(): array
    {
        $todayStart = strtotime('today');
        $todayEnd = strtotime('tomorrow') - 1;

        // Calls that need to be assigned to call center operators
        $unassignedCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $todayStart])
            ->where(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->andWhere(['call_center_employee_id' => null])
            ->count();

        // Total calls waiting for call center action
        $waitingCalls = UrgentCall::find()
            ->where(['>=', 'created_at', $todayStart])
            ->where(['<=', 'created_at', $todayEnd])
            ->andWhere(['status' => UrgentCall::STATUS_NEED_TO_CALL])
            ->count();

        return [
            'unassigned_calls' => (int) $unassignedCalls,
            'waiting_calls' => (int) $waitingCalls
        ];
    }

    /**
     * Calculate percentage change
     * @param int $current
     * @param int $previous
     * @return float
     */
    public function calculatePercentageChange(int $current, int $previous): float
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

    /**
     * Get summary statistics for all operators
     * @param array $operatorStats
     * @return array
     */
    public function calculateSummaryStatistics(array $operatorStats): array
    {
        $totalToday = 0;
        $totalUnansweredToday = 0;
        $totalYesterday = 0;
        $totalUnansweredYesterday = 0;
        $totalMonth = 0;
        $totalUnansweredMonth = 0;

        foreach ($operatorStats as $stats) {
            $totalToday += $stats['today_calls'];
            $totalUnansweredToday += $stats['today_unanswered'];
            $totalYesterday += $stats['yesterday_calls'];
            $totalUnansweredYesterday += $stats['yesterday_unanswered'];
            $totalMonth += $stats['month_calls'];
            $totalUnansweredMonth += $stats['month_unanswered'];
        }

        return [
            'total_today' => $totalToday,
            'total_unanswered_today' => $totalUnansweredToday,
            'total_yesterday' => $totalYesterday,
            'total_unanswered_yesterday' => $totalUnansweredYesterday,
            'total_month' => $totalMonth,
            'total_unanswered_month' => $totalUnansweredMonth,
            'today_answer_rate' => $totalToday > 0 ?
                round((($totalToday - $totalUnansweredToday) / $totalToday) * 100, 1) : 0
        ];
    }
}