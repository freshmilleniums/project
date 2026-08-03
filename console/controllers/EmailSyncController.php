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

        foreach ($accounts as $account) {
            $salt = bin2hex(random_bytes(8));
            $hash = '{SHA512-CRYPT}' . crypt($tempPassword, '$6$' . $salt);

            // 1. Update CRM DB (re-encrypt password)
            $account->plainPassword = $tempPassword;
            if (!$account->save()) {
                echo "FAILED to save CRM password for: {$account->email}\n";
                continue;
            }

            // 2. Insert or update mail DB
            try {
                $exists = Yii::$app->mailDb->createCommand(
                    'SELECT COUNT(*) FROM mail_users WHERE email = :email',
                    [':email' => $account->email]
                )->queryScalar();

                if ($exists) {
                    Yii::$app->mailDb->createCommand()->update(
                        'mail_users',
                        ['password' => $hash],
                        ['email' => $account->email]
                    )->execute();
                    $action = 'update';
                } else {
                    Yii::$app->mailDb->createCommand()->insert('mail_users', [
                        'domain_id'  => 1,
                        'email'      => $account->email,
                        'password'   => $hash,
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

            // 3. Create or update /etc/exim4/passwd
            $command = ($action === 'create') ? 'create' : 'update';
            $result = shell_exec(
                'sudo /usr/local/bin/mailbox-manager.sh ' . $command . ' '
                . escapeshellarg($account->email) . ' '
                . escapeshellarg($hash)
            );
            Yii::info("mailbox-manager {$command} result: {$result}", 'email');

            echo "OK [{$action}]: {$account->email}\n";
        }

        echo "Done.\n";
    }
}