<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\EmailAccount;
use common\models\UserCorporateEmail;
use common\services\EmailSyncService;
use common\models\Companies;

class EmailSyncController extends Controller
{
    /**
     * Sync all active external email accounts
     * Usage: php yii email-sync/external
     */
    public function actionExternal()
    {
        $companies = Companies::find()
            ->where(['status' => Companies::STATUS_RUNNING])
            ->all();

        if (empty($companies)) {
            echo "No running companies found.\n";
            return;
        }

        $syncService = new EmailSyncService();
        $synced = 0;
        $failed = 0;

        foreach ($companies as $company) {
            try {
                Yii::$app->params['company_id'] = $company->id;

                $accounts = EmailAccount::find()
                    ->where(['is_active' => 1])
                    ->all();

                if (empty($accounts)) {
                    continue;
                }

                foreach ($accounts as $account) {
                    try {
                        echo "Syncing: {$account->email}... ";
                        $syncService->syncExternalAccount($account->id);
                        echo "OK\n";
                        $synced++;
                    } catch (\Exception $e) {
                        echo "FAILED: {$e->getMessage()}\n";
                        Yii::error("Sync failed for external account {$account->id}: " . $e->getMessage(), 'email');
                        $failed++;
                    }
                }

            } finally {
                Yii::$app->params['company_id'] = 0;
            }
        }

        echo "Done. Synced: {$synced}, Failed: {$failed}\n";
    }

    /**
     * Sync all active corporate email accounts
     * Usage: php yii email-sync/corporate
     */
    public function actionCorporate()
    {
        $accounts = UserCorporateEmail::find()
            ->where(['is_active' => 1])
            ->all();

        if (empty($accounts)) {
            echo "No active corporate accounts found.\n";
            return;
        }

        $syncService = new EmailSyncService();
        $synced = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                echo "Syncing: {$account->email}... ";
                $syncService->syncCorporateAccount($account->id);
                echo "OK\n";
                $synced++;
            } catch (\Exception $e) {
                echo "FAILED: {$e->getMessage()}\n";
                Yii::error("Sync failed for corporate account {$account->id}: " . $e->getMessage(), 'email');
                $failed++;
            }
        }

        echo "Done. Synced: {$synced}, Failed: {$failed}\n";
    }

    /**
     * Sync all (external + corporate)
     * Usage: php yii email-sync/all
     */
    public function actionAll()
    {
        $this->actionExternal();
        $this->actionCorporate();
    }

    /**
     * Fix corporate accounts with empty passwords or missing mail_users records
     * Usage: php yii email-sync/fix-empty-passwords
     */
    public function actionFixEmptyPasswords()
    {
        $tempPassword = 'Tt123456';

        $accounts = \common\models\UserCorporateEmail::find()->all();

        if (empty($accounts)) {
            echo "No corporate accounts found.\n";
            return;
        }

        echo "Processing " . count($accounts) . " accounts.\n";
        $fixed = 0;
        $skipped = 0;

        foreach ($accounts as $account) {

            // Check if password already exists in mail DB
            try {
                $mailUser = Yii::$app->mailDb->createCommand(
                    'SELECT password FROM mail_users WHERE email = :email',
                    [':email' => $account->email]
                )->queryOne();
            } catch (\Exception $e) {
                echo "FAILED check mail DB for {$account->email}: " . $e->getMessage() . "\n";
                continue;
            }

            // Skip if record exists and password is not empty
            if ($mailUser && !empty($mailUser['password'])) {
                echo "SKIP (password exists): {$account->email}\n";
                $skipped++;
                continue;
            }

            // Generate SHA512-CRYPT hash for Dovecot/Exim
            $salt = bin2hex(random_bytes(8));
            $hash = crypt($tempPassword, '$6$' . $salt);

            // 1. Update CRM DB — re-encrypt password with Yii security
            $account->plainPassword = $tempPassword;
            if (!$account->save()) {
                echo "FAILED to save CRM password for: {$account->email}\n";
                continue;
            }

            // 2. Insert or update mail DB
            try {
                // Get domain_id dynamically — supports multi-domain setup
                $domain = substr(strrchr($account->email, '@'), 1);
                $localPart = explode('@', $account->email)[0];

                $domainId = Yii::$app->mailDb->createCommand(
                    'SELECT id FROM mail_domains WHERE name = :name',
                    [':name' => $domain]
                )->queryScalar();

                if (!$domainId) {
                    echo "FAILED: domain not found in mail_domains for {$account->email}\n";
                    continue;
                }

                if ($mailUser) {
                    // Record exists but password was empty — update only password
                    Yii::$app->mailDb->createCommand()->update(
                        'mail_users',
                        ['password' => $hash],
                        ['email' => $account->email]
                    )->execute();
                    $action = 'update';
                } else {
                    // No record at all — create with full data
                    Yii::$app->mailDb->createCommand()->insert('mail_users', [
                        'domain_id'  => $domainId,
                        'email'      => $account->email,
                        'password'   => $hash,
                        'home'       => '/var/mail/vhosts/' . $domain . '/' . $localPart,
                        'is_active'  => 1,
                        'created_at' => time(),
                    ])->execute();
                    $action = 'create';
                }
            } catch (\Exception $e) {
                echo "FAILED mail DB for {$account->email}: " . $e->getMessage() . "\n";
                Yii::error("Failed to sync mail_user for {$account->email}: " . $e->getMessage());
                continue;
            }

            // 3. Sync to /etc/exim4/passwd via mailbox-manager.sh
            $result = shell_exec(
                'sudo /usr/local/bin/mailbox-manager.sh ' . $action . ' '
                . escapeshellarg($account->email) . ' '
                . escapeshellarg($hash)
            );
            Yii::info("mailbox-manager {$action} result: {$result}", 'email');

            echo "OK [{$action}]: {$account->email}\n";
            $fixed++;
        }

        echo "Done. Fixed: {$fixed}, Skipped: {$skipped}\n";
    }

    /**
     * Create missing corporate email accounts for users who don't have them
     * Usage: php yii email-sync/provision-missing
     */
    public function actionProvisionMissing()
    {
        $eligibleRoles = [
            'super-administrator',
            'administrator',
            'phone-operator',
            'email-task-operator',
            'employee'
        ];

        // Find users without corporate email record
        $users = \common\models\User::find()
            ->joinWith('corporateEmail', false)
            ->where(['user_corporate_emails.id' => null])
            ->andWhere(['!=', 'user.status', \common\models\User::STATUS_DELETED])
            ->andWhere(['is not', 'user.company_id', null])
            ->all();

        if (empty($users)) {
            echo "All users already have corporate email accounts.\n";
            return;
        }

        echo "Found " . count($users) . " users without corporate email.\n";

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($users as $user) {
            // Check user role is eligible
            $auth = Yii::$app->authManager;
            $userRoles = array_keys($auth->getRolesByUser($user->id));
            $hasEligibleRole = !empty(array_intersect($userRoles, $eligibleRoles));

            if (!$hasEligibleRole) {
                echo "SKIP (no eligible role): {$user->email}\n";
                $skipped++;
                continue;
            }

            // Determine domain — from company or from params for administrators
            $company = $user->company;
            $isAdmin = !empty(array_intersect($userRoles, ['super-administrator', 'administrator']));

            if ($company && !empty($company->email_domain)) {
                // Regular user — use company domain
                $domain = $company->email_domain;
            } elseif ($isAdmin) {
                // Administrator — use admin panel domain from params
                $domain = Yii::$app->params['corporateEmailDomain'] ?? null;
                if (!$domain) {
                    echo "SKIP (corporateEmailDomain not configured in params): {$user->email}\n";
                    $skipped++;
                    continue;
                }
            } else {
                echo "SKIP (no email domain for company): {$user->email}\n";
                $skipped++;
                continue;
            }

            // Build corporate email address
            $localPart = preg_replace('/[^a-z0-9]/', '', strtolower($user->first_name)) . '.'
                . preg_replace('/[^a-z0-9]/', '', strtolower($user->last_name));

            $corporateEmail = $localPart . '@' . $domain;

            $tempPassword = 'Tt123456';

            // Generate SHA512-CRYPT hash for Dovecot/Exim
            $salt = bin2hex(random_bytes(8));
            $hash = crypt($tempPassword, '$6$' . $salt);

            // 1. Create record in CRM DB
            $corporateEmailModel = new \common\models\UserCorporateEmail();
            $corporateEmailModel->user_id = $user->id;
            $corporateEmailModel->email = $corporateEmail;
            $corporateEmailModel->plainPassword = $tempPassword;
            $corporateEmailModel->is_active = 1;

            if (!$corporateEmailModel->save()) {
                echo "FAILED to save CRM record for: {$user->email} -> {$corporateEmail}\n";
                echo "  Errors: " . json_encode($corporateEmailModel->getErrors()) . "\n";
                $failed++;
                continue;
            }

            // 2. Insert into mail DB
            try {
                $domainId = Yii::$app->mailDb->createCommand(
                    'SELECT id FROM mail_domains WHERE name = :name',
                    [':name' => $domain]
                )->queryScalar();

                if (!$domainId) {
                    // Register domain in mail DB if not exists
                    Yii::$app->mailDb->createCommand()->insert('mail_domains', [
                        'name'      => $domain,
                        'is_active' => 1,
                    ])->execute();
                    $domainId = Yii::$app->mailDb->getLastInsertID();
                    echo "  Registered new domain in mail DB: {$domain}\n";

                    // Create mail directory for new domain
                    $mailDir = '/var/mail/vhosts/' . $domain;
                    if (!is_dir($mailDir)) {
                        shell_exec('sudo mkdir -p ' . escapeshellarg($mailDir));
                        shell_exec('sudo chown -R vmail:vmail ' . escapeshellarg($mailDir));
                        shell_exec('sudo chmod 770 ' . escapeshellarg($mailDir));
                        echo "  Created mail directory: {$mailDir}\n";
                    }
                }

                $exists = Yii::$app->mailDb->createCommand(
                    'SELECT COUNT(*) FROM mail_users WHERE email = :email',
                    [':email' => $corporateEmail]
                )->queryScalar();

                if (!$exists) {
                    Yii::$app->mailDb->createCommand()->insert('mail_users', [
                        'domain_id'  => $domainId,
                        'email'      => $corporateEmail,
                        'password'   => $hash,
                        'home'       => '/var/mail/vhosts/' . $domain . '/' . $localPart,
                        'is_active'  => 1,
                        'created_at' => time(),
                    ])->execute();
                }
            } catch (\Exception $e) {
                echo "FAILED mail DB for {$corporateEmail}: " . $e->getMessage() . "\n";
                Yii::error("provision-missing mail DB failed for {$corporateEmail}: " . $e->getMessage());
                $failed++;
                continue;
            }

            // 3. Create mailbox in /etc/exim4/passwd
            $result = shell_exec(
                'sudo /usr/local/bin/mailbox-manager.sh create '
                . escapeshellarg($corporateEmail) . ' '
                . escapeshellarg($hash)
            );
            Yii::info("mailbox-manager create result: {$result}", 'email');

            echo "OK: {$user->email} -> {$corporateEmail}\n";
            $created++;
        }

        echo "Done. Created: {$created}, Skipped: {$skipped}, Failed: {$failed}\n";

        if ($created > 0) {
            echo "\nNote: temporary password 'Tt123456' was set for all created accounts.\n";
            echo "Users should change their passwords via CRM interface.\n";
        }
    }

}