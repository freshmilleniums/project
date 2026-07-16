<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use backend\models\User;

class UserController extends Controller
{
    public $firstName = 'Admin';
    public $lastName = 'Admin';

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['firstName', 'lastName']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'f' => 'firstName',
            'l' => 'lastName',
        ]);
    }

    public function actionCreateSuperAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        $user->first_name = $this->firstName;
        $user->last_name = $this->lastName;
        $user->status = User::STATUS_ACTIVE;
        $user->company_id = 0;
        $user->role = 'super-administrator';
        $user->created_at = time();
        $user->updated_at = time();

        $user->setPassword($password);
        $user->generateAuthKey();
        $user->generateEmailVerificationToken();

        // Validate only critical fields — other fields are not required for super-admin
        if (!$user->validate(['email', 'password_hash', 'auth_key', 'status'])) {
            $this->stderr("Validation error:\n", \yii\helpers\Console::FG_RED);
            foreach ($user->errors as $field => $errors) {
                foreach ($errors as $error) {
                    $this->stderr("  {$field}: {$error}\n", \yii\helpers\Console::FG_RED);
                }
            }
            return Controller::EXIT_CODE_ERROR;
        }

        if ($user->save(false)) {
            $auth = Yii::$app->authManager;
            $role = $auth->getRole('super-administrator');

            if ($role) {
                $auth->assign($role, $user->id);
                $this->stdout("Super administrator created successfully!\n", \yii\helpers\Console::FG_GREEN);
                $this->stdout("Email: {$email}\n");
                $this->stdout("Password: {$password}\n");
                $this->stdout("Name: {$user->first_name} {$user->last_name}\n");
                $this->stdout("User ID: {$user->id}\n");
                return Controller::EXIT_CODE_NORMAL;
            } else {
                $this->stderr("Role 'super-administrator' not found. Run migrations first!\n", \yii\helpers\Console::FG_RED);
                $user->delete();
                return Controller::EXIT_CODE_ERROR;
            }
        } else {
            $this->stderr("Error saving user:\n", \yii\helpers\Console::FG_RED);
            foreach ($user->errors as $field => $errors) {
                foreach ($errors as $error) {
                    $this->stderr("  {$field}: {$error}\n", \yii\helpers\Console::FG_RED);
                }
            }
            return Controller::EXIT_CODE_ERROR;
        }
    }
}