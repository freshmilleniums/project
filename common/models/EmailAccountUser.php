<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use backend\models\User;

/**
 * EmailAccountUser model
 *
 * @property int $id
 * @property int $email_account_id
 * @property int $user_id
 * @property int $created_at
 *
 * @property EmailAccount $emailAccount
 * @property User $user
 */
class EmailAccountUser extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%email_account_users}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['email_account_id', 'user_id'], 'required'],
            [['email_account_id', 'user_id'], 'integer'],

            [['email_account_id'], 'exist', 'skipOnError' => true, 'targetClass' => EmailAccount::class, 'targetAttribute' => ['email_account_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],

            [['email_account_id', 'user_id'], 'unique', 'targetAttribute' => ['email_account_id', 'user_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'email_account_id' => 'Email Account',
            'user_id' => 'User',
            'created_at' => 'Created At',
        ];
    }

    public function getEmailAccount()
    {
        return $this->hasOne(EmailAccount::class, ['id' => 'email_account_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}