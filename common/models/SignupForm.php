<?php

namespace common\models;

use Yii;
use yii\base\Model;
use common\models\User;

/**
 * Signup form
 */
class SignupForm extends Model
{
    public $first_name;
    public $last_name;
    public $email;
    public $address;
    public $phone_number;
    public $city;
    public $state;
    public $zip_code;
    public $password;
    public $password_repeat;


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['first_name', 'last_name', 'address', 'phone_number', 'city', 'state', 'zip_code', 'email', 'password'], 'required'],
            [['first_name', 'last_name', 'city', 'state', 'zip_code'], 'string', 'max' => 255],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This email address has already been taken.'],
            //['phone_number', 'match', 'pattern' => '/^\+1\d{10}$/', 'message' => 'Phone number should be in the format +1XXXXXXXXXX'],
            ['password', 'string', 'min' => Yii::$app->params['user.passwordMinLength']],
            ['password_repeat', 'required'],
            ['password_repeat', 'compare', 'compareAttribute' => 'password'],
            [['first_name', 'last_name', 'address', 'city', 'state', 'zip_code', 'email', 'password', 'password_repeat'], 'filter', 'filter' => 'trim'],
            [['first_name', 'last_name', 'address', 'city', 'state', 'zip_code', 'email', 'password', 'password_repeat'], 'filter', 'filter' => 'strip_tags'],
        ];
    }

    /**
     * Signs user up.
     *
     * @return bool whether the creating new account was successful and email was sent
     */
    public function signup()
    {
        if (!$this->validate()) {
            return null;
        }
        
        $user = new User();
        $user->first_name = $this->first_name;
        $user->last_name = $this->last_name;
        $user->email = $this->email;
        $user->address = $this->address;
        $user->phone_number = $this->phone_number;
        $user->city = $this->city;
        $user->state = $this->state;
        $user->zip_code = $this->zip_code;
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateEmailVerificationToken();

        //return $user->save() && $this->sendEmail($user);
        if ($user->save()) {
            return $user;
        }

        return false;
    }

    /**
     * Sends confirmation email to user
     * @param User $user user model to with email should be send
     * @return bool whether the email was sent
     */
    protected function sendEmail($user)
    {
        return Yii::$app
            ->mailer
            ->compose(
                ['html' => 'emailVerify-html', 'text' => 'emailVerify-text'],
                ['user' => $user]
            )
            ->setFrom([Yii::$app->params['supportEmail'] => Yii::$app->name . ' robot'])
            ->setTo($this->email)
            ->setSubject('Account registration at ' . Yii::$app->name)
            ->send();
    }
}
