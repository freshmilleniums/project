<?php

namespace common\services;

use Yii;
use yii\data\ActiveDataProvider;
use backend\models\User;
use backend\models\UserSearch;
use common\models\UserComments;
use common\models\UrgentCall;

/**
 * Service for managing user roles and permissions
 */
class UserService
{
    /**
     * Role hierarchy mapping
     */
    private const ROLE_DISPLAY_NAMES = [
        'super-administrator' => 'Super Administrator',
        'administrator' => 'Administrator',
        'phone-operator' => 'Phone Operator',
        'email-task-operator' => 'Email/Task Operator',
        'employee' => 'Employee',
    ];

    /**
     *  status groups mapping
     */
    private const EMPLOYEE_STATUS_GROUPS = [
        'not_processed' => [
            'label' => 'Not Processed',
            'substatuses' => [1]  // SUBSTATUS_NOT_PROCESSED
        ],
        'new_applicants' => [
            'label' => 'New Applicants',
            'substatuses' => [2]  // SUBSTATUS_NEW_APPLICANT
        ],
        'primary_contact' => [
            'label' => 'Primary Contact',
            'substatuses' => [3]  // SUBSTATUS_PRIMARY_CONTACT
        ],
        'interview_completed' => [
            'label' => 'Interview Completed',
            'substatuses' => [4]  // SUBSTATUS_INTERVIEW_COMPLETED
        ],
        'contract_sent' => [
            'label' => 'Contract Sent',
            'substatuses' => [5]  // SUBSTATUS_CONTRACT_SENT
        ],
        'training' => [
            'label' => 'Training',
            'substatuses' => [6, 7, 11]  // TRAINING, FINAL_ASSIGNMENT, UNCOMPLETED_TASK
        ],
        'active_employees' => [
            'label' => 'Active Employees',
            'substatuses' => [8]  // SUBSTATUS_ACTIVE_EMPLOYEE
        ],
        'refused' => [
            'label' => 'Refused',
            'substatuses' => [9]  // SUBSTATUS_CONTRACT_REFUSED
        ],
        'archived' => [
            'label' => 'Archived',
            'substatuses' => [10]  // SUBSTATUS_ARCHIVED
        ],
    ];

    /**
     * Get available role keys based on current user's role
     * @param int $userId
     * @return array
     */
    public function getAvailableRoleKeysForUser(int $userId): array
    {
        $userRole = Yii::$app->authManager->getRolesByUser($userId);
        $userRoleKey = key($userRole);

        return match ($userRoleKey) {
            'phone-operator', 'email-task-operator' => ['employee'],
            'super-administrator' => array_keys(self::ROLE_DISPLAY_NAMES),
            'administrator' => [
                 'phone-operator', 'email-task-operator', 'employee'
            ],
            default => []
        };
    }

    /**
     * Get available roles based on current user's role
     * @param int $userId
     * @return array
     */
    public function getAvailableRolesForUser(int $userId): array
    {
        $roleKeys = $this->getAvailableRoleKeysForUser($userId);

        return array_intersect_key(self::ROLE_DISPLAY_NAMES, array_flip($roleKeys));
    }

    /**
     * Check if user can manage specific role
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function canUserManageRole(int $userId, string $role): bool
    {
        if ($role !== 'employee') {
            return false;
        }

        $userRole = Yii::$app->authManager->getRolesByUser($userId);
        $userRoleKey = key($userRole);

        return in_array($userRoleKey, ['phone-operator', 'email-task-operator']);
    }

    /**
     * Get tabs data for roles (determines available roles automatically)
     * @return array
     */
    public function getTabsDataForRoles(): array
    {
        $currentUserId = Yii::$app->user->id;
        $availableRoles = $this->getAvailableRolesForUser($currentUserId);

        if (empty($availableRoles)) {
            return [];
        }

        $tabs = [];
        $firstRole = array_key_first($availableRoles);

        foreach ($availableRoles as $roleName => $roleDisplayName) {
            $dataProvider = $this->createDataProviderForRole($roleName);

            $tabs[] = [
                'id' => "tab-$roleName",
                'role' => $roleName,
                'label' => $roleDisplayName,
                'dataProvider' => $dataProvider,
                'active' => $roleName === $firstRole,
            ];
        }

        return $tabs;
    }

    /**
     * Create data provider for specific role
     * @param string $roleName
     * @return ActiveDataProvider
     */
    private function createDataProviderForRole(string $roleName): ActiveDataProvider
    {
        $query = User::find()
            ->alias('user') // Add alias for user table
            ->innerJoin('auth_assignment aa', 'aa.user_id = user.id') // Add alias for auth_assignment
            ->where(['aa.item_name' => $roleName]);

        if (in_array($roleName, ['super-administrator', 'administrator'])) {
            $query->andWhere(['user.company_id' => 0]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => [
                    'id' => [
                        'asc' => ['user.id' => SORT_ASC],
                        'desc' => ['user.id' => SORT_DESC],
                    ],
                    'first_name' => [
                        'asc' => ['user.first_name' => SORT_ASC],
                        'desc' => ['user.first_name' => SORT_DESC],
                    ],
                    'last_name' => [
                        'asc' => ['user.last_name' => SORT_ASC],
                        'desc' => ['user.last_name' => SORT_DESC],
                    ],
                    'email' => [
                        'asc' => ['user.email' => SORT_ASC],
                        'desc' => ['user.email' => SORT_DESC],
                    ],
                    'phone_number' => [
                        'asc' => ['user.phone_number' => SORT_ASC],
                        'desc' => ['user.phone_number' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => ['user.created_at' => SORT_ASC],
                        'desc' => ['user.created_at' => SORT_DESC],
                    ],
                    'substatus' => [
                        'asc' => ['user.substatus' => SORT_ASC],
                        'desc' => ['user.substatus' => SORT_DESC],
                    ],
                ],
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ]
            ],
        ]);
    }

    /**
     * Get tabs data for employee grouped by status categories
     * @return array
     */
    public function getTabsDataForEmployeesBySubstatus(): array
    {
        $currentUserId = Yii::$app->user->id;

        if (!$this->canUserManageRole($currentUserId, 'employee')) {
            return [];
        }

        // Get current user's role
        $userRole = Yii::$app->authManager->getRolesByUser($currentUserId);
        $userRoleKey = key($userRole);

        // Define available status groups based on user role
        $availableGroups = match ($userRoleKey) {
            'phone-operator' => ['not_processed', 'new_applicants', 'primary_contact', 'interview_completed'],
            'email-task-operator' => ['contract_sent', 'training', 'active_employees', 'refused', 'archived'],
            default => []
        };

        if (empty($availableGroups)) {
            return [];
        }

        $statusCounts = $this->getEmployeeCountsByStatusGroups($availableGroups);
        $urgentCallsCount = $this->getUrgentCallsCount($userRoleKey);

        $tabs = [];
        $firstGroup = $availableGroups[0];
        $allUserIds = [];

        foreach ($availableGroups as $groupKey) {
            if ($groupKey === 'urgent_calls') {
                $filterModel = null;
                $dataProvider = $this->createDataProviderForUrgentCalls($userRoleKey);
                $count = $urgentCallsCount;

                // Highlight count for call-center when there are urgent calls
                if ($userRoleKey === 'call-operator' && $count > 0) {
                    $label = 'Urgent Calls (<span class="urgent-count-highlight">' . $count . '</span>)';
                } else {
                    $label = 'Urgent Calls';
                }

                // Check for unconfirmed calls for HR notification
                $hasHrCallsNeedingConfirmation = false;
                if ($userRoleKey === 'hr-specialist') {
                    $hasHrCallsNeedingConfirmation = UrgentCall::find()
                        ->where([
                            'status' => UrgentCall::STATUS_CALLED,
                            'is_confirmed' => 0,
                            'hr_employee_id' => Yii::$app->user->id  // Only current HR user's calls
                        ])
                        ->exists();
                }

                $labelClass = '';
                if ($hasHrCallsNeedingConfirmation) {
                    $labelClass = 'hr-urgent-tab-highlight';
                }
            } else {
                $groupData = self::EMPLOYEE_STATUS_GROUPS[$groupKey];
                $count = $statusCounts[$groupKey] ?? 0;
                $label = $groupData['label'];
                $labelClass = '';
                $filterModel = new UserSearch();
                $dataProvider = $this->createDataProviderForEmployeesByStatusGroup($groupKey, $filterModel);

                $models = $dataProvider->getModels();
                $userIds = array_column($models, 'id');
                $allUserIds = array_merge($allUserIds, $userIds);
            }

            $tabs[] = [
                'id' => "tab-employee-$groupKey",
                'group' => $groupKey,
                //'label' => "$label ($count)",
                'label' => $groupKey === 'urgent_calls' ? $label : "$label ($count)",
                'labelClass' => $labelClass,
                'dataProvider' => $dataProvider,
                'filterModel' => $filterModel,
                'active' => $groupKey === $firstGroup,
            ];
        }

        // Add comments for regular employee tabs
        $allComments = $this->getCommentsForUsers(array_unique($allUserIds), $currentUserId);

        foreach ($tabs as &$tab) {
            if ($tab['group'] !== 'urgent_calls') {
                $tab['comments'] = $allComments;
            }
        }

        return $tabs;
    }

    /**
     * Get urgent calls count based on user role
     * @param string $userRoleKey
     * @return int
     */
    private function getUrgentCallsCount(string $userRoleKey): int
    {
        $query = UrgentCall::find()->alias('uc');

        if ($userRoleKey === 'call-operator') {
            // Call operators see only calls that need to be called
            $query->where(['uc.status' => UrgentCall::STATUS_NEED_TO_CALL]);
        } elseif ($userRoleKey === 'hr-specialist') {
            // HR sees all calls
            $query->where(['!=', 'uc.is_confirmed', 1]);
        }

        return $query->count();
    }

    /**
     * Create data provider for urgent calls
     * @param string $userRoleKey
     * @return ActiveDataProvider
     */
    private function createDataProviderForUrgentCalls(string $userRoleKey): ActiveDataProvider
    {
        $query = UrgentCall::find()
            ->alias('uc')
            ->innerJoin('user u', 'u.id = uc.user_id')
            ->with(['user', 'hrEmployee', 'callCenterEmployee']);

        if ($userRoleKey === 'call-operator') {
            // Call operators see only calls that need to be called
            $query->where(['uc.status' => UrgentCall::STATUS_NEED_TO_CALL]);
        } elseif ($userRoleKey === 'hr-specialist') {
            // HR sees all unconfirmed calls
            $query->where(['!=', 'uc.is_confirmed', 1]);
        }

        return new ActiveDataProvider([
            'query' => $query->orderBy(['uc.created_at' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => [
                    'created_at' => [
                        'asc' => ['uc.created_at' => SORT_ASC],
                        'desc' => ['uc.created_at' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => ['uc.status' => SORT_ASC],
                        'desc' => ['uc.status' => SORT_DESC],
                    ],
                ],
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ]
            ],
        ]);
    }

    /**
     * @param array $userIds
     * @param int $commentedBy
     * @return array
     */
    private function getCommentsForUsers(array $userIds, int $commentedBy): array
    {
        if (empty($userIds)) {
            return [];
        }

        $comments = UserComments::find()
            ->where([
                'user_id' => $userIds,
                'commented_by' => $commentedBy
            ])
            ->orderBy('created_at DESC')
            ->all();

        $groupedComments = [];
        foreach ($comments as $comment) {
            $groupedComments[$comment->user_id][] = $comment;
        }

        return $groupedComments;
    }

    /**
     * Get employee counts by status groups
     * @return array
     */
    private function getEmployeeCountsByStatusGroups(array $availableGroups = null): array
    {
        $counts = [];
        $groupsToCount = $availableGroups ?? array_keys(self::EMPLOYEE_STATUS_GROUPS);

        foreach ($groupsToCount as $groupKey) {
            if ($groupKey === 'urgent_calls') {
                continue; // Skip urgent calls in this method
            }

            if (!isset(self::EMPLOYEE_STATUS_GROUPS[$groupKey])) {
                continue;
            }

            $groupData = self::EMPLOYEE_STATUS_GROUPS[$groupKey];
            $query = User::find()
                ->innerJoin('auth_assignment', 'auth_assignment.user_id = user.id')
                ->where([
                    'auth_assignment.item_name' => 'employee',
                    'user.substatus' => $groupData['substatuses']
                ]);

            $counts[$groupKey] = $query->count();
        }

        return $counts;
    }

    private function createDataProviderForEmployeesByStatusGroup(string $groupKey, UserSearch $filterModel = null): ActiveDataProvider
    {
        $groupData = self::EMPLOYEE_STATUS_GROUPS[$groupKey];

        // Check if this tab needs attempts counter
        $hasAttemptsCounter = in_array($groupKey, ['call_again_interview', 'call_again_contract']);

        if ($filterModel) {
            // Load search parameters
            $filterModel->load(Yii::$app->request->queryParams);

            // Get data provider from search method
            $dataProvider = $filterModel->search(Yii::$app->request->queryParams);

            // Apply additional constraints for employee role and substatus
            $dataProvider->query
                ->alias('user')
                ->innerJoin('auth_assignment aa', 'aa.user_id = user.id')
                ->andWhere([
                    'aa.item_name' => 'employee',
                    'user.substatus' => $groupData['substatuses']
                ]);

            // Add call attempts aggregation for "Call Again" tabs
            if ($hasAttemptsCounter) {
                $context = ($groupKey === 'call_again_interview')
                    ? \common\models\CallAttempt::CONTEXT_INTERVIEW
                    : \common\models\CallAttempt::CONTEXT_CONTRACT;

                $dataProvider->query->leftJoin(
                    "(SELECT 
                    user_id,
                    COUNT(*) as attempts_count,
                    MAX(created_at) as last_attempt_at
                 FROM call_attempts
                 WHERE context = '{$context}'
                 GROUP BY user_id) ca",
                    'ca.user_id = user.id'
                );

                $dataProvider->query->select([
                    'user.*',
                    'ca.attempts_count',
                    'ca.last_attempt_at'
                ]);
            }

            $dataProvider->query->orderBy(['user.created_at' => SORT_DESC]);

            // Configure sorting with proper aliases
            $dataProvider->sort->attributes = array_merge($dataProvider->sort->attributes, [
                'id' => [
                    'asc' => ['user.id' => SORT_ASC],
                    'desc' => ['user.id' => SORT_DESC],
                ],
                'first_name' => [
                    'asc' => ['user.first_name' => SORT_ASC],
                    'desc' => ['user.first_name' => SORT_DESC],
                ],
                'last_name' => [
                    'asc' => ['user.last_name' => SORT_ASC],
                    'desc' => ['user.last_name' => SORT_DESC],
                ],
                'email' => [
                    'asc' => ['user.email' => SORT_ASC],
                    'desc' => ['user.email' => SORT_DESC],
                ],
                'phone_number' => [
                    'asc' => ['user.phone_number' => SORT_ASC],
                    'desc' => ['user.phone_number' => SORT_DESC],
                ],
                'created_at' => [
                    'asc' => ['user.created_at' => SORT_ASC],
                    'desc' => ['user.created_at' => SORT_DESC],
                ],
                'substatus' => [
                    'asc' => ['user.substatus' => SORT_ASC],
                    'desc' => ['user.substatus' => SORT_DESC],
                ],
            ]);

            if ($hasAttemptsCounter) {
                $dataProvider->sort->attributes['attempts_count'] = [
                    'asc' => ['ca.attempts_count' => SORT_ASC],
                    'desc' => ['ca.attempts_count' => SORT_DESC],
                ];
            }

            $dataProvider->pagination = [
                'pageSize' => 10,
            ];

            return $dataProvider;
        }

        // Fallback if no filter model provided
        $query = User::find()
            ->alias('user')
            ->innerJoin('auth_assignment aa', 'aa.user_id = user.id')
            ->where([
                'aa.item_name' => 'employee',
                'user.substatus' => $groupData['substatuses']
            ]);

        $hasAttemptsCounter = in_array($groupKey, ['call_again_interview', 'call_again_contract']);

        if ($hasAttemptsCounter) {
            $context = ($groupKey === 'call_again_interview')
                ? \common\models\CallAttempt::CONTEXT_INTERVIEW
                : \common\models\CallAttempt::CONTEXT_CONTRACT;

            $query->leftJoin(
                "(SELECT 
                user_id,
                COUNT(*) as attempts_count,
                MAX(created_at) as last_attempt_at
             FROM call_attempts
             WHERE context = '{$context}'
             GROUP BY user_id) ca",
                'ca.user_id = user.id'
            );

            $query->select([
                'user.*',
                'ca.attempts_count',
                'ca.last_attempt_at'
            ]);
        }

        $query->orderBy(['user.created_at' => SORT_DESC]);

        $sortAttributes = [
            'id' => [
                'asc' => ['user.id' => SORT_ASC],
                'desc' => ['user.id' => SORT_DESC],
            ],
            'first_name' => [
                'asc' => ['user.first_name' => SORT_ASC],
                'desc' => ['user.first_name' => SORT_DESC],
            ],
            'last_name' => [
                'asc' => ['user.last_name' => SORT_ASC],
                'desc' => ['user.last_name' => SORT_DESC],
            ],
            'email' => [
                'asc' => ['user.email' => SORT_ASC],
                'desc' => ['user.email' => SORT_DESC],
            ],
            'phone_number' => [
                'asc' => ['user.phone_number' => SORT_ASC],
                'desc' => ['user.phone_number' => SORT_DESC],
            ],
            'created_at' => [
                'asc' => ['user.created_at' => SORT_ASC],
                'desc' => ['user.created_at' => SORT_DESC],
            ],
            'substatus' => [
                'asc' => ['user.substatus' => SORT_ASC],
                'desc' => ['user.substatus' => SORT_DESC],
            ],
        ];

        if ($hasAttemptsCounter) {
            $sortAttributes['attempts_count'] = [
                'asc' => ['ca.attempts_count' => SORT_ASC],
                'desc' => ['ca.attempts_count' => SORT_DESC],
            ];
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10],
            'sort' => [
                'attributes' => $sortAttributes,
                'defaultOrder' => ['created_at' => SORT_DESC]
            ],
        ]);
    }


    /**
     * Get allowed substatuses for a specific group (for use in User model)
     * @param string $groupKey
     * @return array
     */
    public function getAllowedSubstatusesForGroup(string $groupKey): array
    {
        if (!isset(self::EMPLOYEE_STATUS_GROUPS[$groupKey])) {
            return [];
        }

        return self::EMPLOYEE_STATUS_GROUPS[$groupKey]['substatuses'];
    }

    /**
     * Determine which tab group a user belongs to based on their substatus
     *
     * @param User $user
     * @return string|null
     */
    public function determineTabGroupForUser(User $user): ?string
    {
        $substatus = $user->substatus;

        foreach (self::EMPLOYEE_STATUS_GROUPS as $groupKey => $groupData) {
            if (in_array($substatus, $groupData['substatuses'])) {
                return $groupKey;
            }
        }

        return null;
    }

    /**
     * Get context for call attempts based on tab group
     *
     * @param string $tabGroup
     * @return string|null
     */
    public function getContextForTabGroup(string $tabGroup): ?string
    {
        if ($tabGroup === 'call_again_interview') {
            return \common\models\CallAttempt::CONTEXT_INTERVIEW;
        }

        if ($tabGroup === 'call_again_contract') {
            return \common\models\CallAttempt::CONTEXT_CONTRACT;
        }

        return null;
    }
}