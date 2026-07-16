<?php

namespace backend\models;

use Yii;
use yii\base\Model;
use common\models\User;

class ChangePasswordForm extends Model
{
    public $new_password;
    public $confirm_password;

    /**
     * @var User
     */
    private $_user;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['new_password', 'confirm_password'], 'required'],
            ['new_password', 'string', 'min' => 4, 'max' => 255],
            ['confirm_password', 'string', 'min' => 4, 'max' => 255],
            ['confirm_password', 'compare', 'compareAttribute' => 'new_password', 'message' => 'Passwords do not match.'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'new_password' => 'New Password',
            'confirm_password' => 'Confirm Password',
        ];
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    /**
     * @return bool
     */
    public function changePassword()
    {
        if (!$this->validate()) {
            return false;
        }

        if (!$this->_user) {
            $this->addError('new_password', 'User not found.');
            return false;
        }

        $this->_user->setPassword($this->new_password);

        if ($this->_user->save()) {
            return true;
        }

        foreach ($this->_user->getErrors() as $attribute => $errors) {
            foreach ($errors as $error) {
                $this->addError('new_password', $error);
            }
        }

        return false;
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->_user;
    }
}